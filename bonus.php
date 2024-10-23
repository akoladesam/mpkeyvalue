<?php

    class MainStore {

        private $filename;
        private $nodes = [
            'http://node1.example.com',
            'http://node2.example.com',
            'http://node3.example.com',
        ];

        public function __construct($filename = 'db.json') {

            $this->filename = $filename;
            if (!file_exists($this->filename)) {
                file_put_contents($this->filename, json_encode([]));
            }

        }

        # This to load data from the file
        private function loadData() {

            $file = fopen($this->filename, 'r');
            if (flock($file, LOCK_SH)) { # Shared lock for reading
                $data = json_decode(file_get_contents($this->filename), true);
                flock($file, LOCK_UN); # Unlock file
            }
            fclose($file);
            return $data ?? [];

        }

        private function replicateData($data, $healthyNodes) {
            foreach ($healthyNodes as $node) {
                $ch = curl_init($node . '/replicate'); // Assume there's an endpoint for replication
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                curl_close($ch);
            }
        }
    
        private function checkNodeHealth() {
            $healthyNodes = [];
            foreach ($this->nodes as $node) {
                $ch = curl_init($node . '/health'); // Assume there's a health-check endpoint
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                if (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) {
                    $healthyNodes[] = $node; // Add to healthy nodes if response is OK
                }
                curl_close($ch);
            }
            return $healthyNodes;
        }

        public function put($key, $value) {

            $file = fopen($this->filename, 'r+');
            if (flock($file, LOCK_EX)) { # Exclusive lock for writing
                $data = json_decode(file_get_contents($this->filename), true) ?? [];
                $data[$key] = $value;
                ftruncate($file, 0);
                rewind($file);
                fwrite($file, json_encode($data));
                flock($file, LOCK_UN); # Unlock file
            }
            fclose($file);
            return "OK";

        }

        public function read($key) {

            $data = $this->loadData();
            return isset($data[$key]) ? $data[$key] : "NOT_FOUND";

        }

        public function readKeyRange($startKey, $endKey) {

            $data = $this->loadData();
            $data_result = [];
            foreach ($data as $key => $value) {
                if ($key >= $startKey && $key <= $endKey) {
                    $data_result[$key] = $value;
                }
            }
            return $data_result;

        }

        public function batchPut($keys, $values) {

            if (count($keys) != count($values)) {
                return "ERROR!!! Keys and values count are not the same.";
            }

            $file = fopen($this->filename, 'r+');
            if (flock($file, LOCK_EX)) { # Exclusive lock for writing
                $data = json_decode(file_get_contents($this->filename), true) ?? [];
                for ($i = 0; $i < count($keys); $i++) {
                    $data[$keys[$i]] = $values[$i];
                }
                ftruncate($file, 0);
                rewind($file);
                fwrite($file, json_encode($data));
                flock($file, LOCK_UN); # Unlock file
            }
            fclose($file);
            return "OK";

        }

        public function delete($key) {

            $file = fopen($this->filename, 'r+');
            if (flock($file, LOCK_EX)) { # Exclusive lock for writing
                $data = json_decode(file_get_contents($this->filename), true) ?? [];
                if (isset($data[$key])) {
                    unset($data[$key]);
                    ftruncate($file, 0);
                    rewind($file);
                    fwrite($file, json_encode($data));
                    flock($file, LOCK_UN); # Unlock file
                    fclose($file);
                    return "OK";
                }
                flock($file, LOCK_UN); # Unlock file if key doesn't exist
            }
            fclose($file);
            return "NOT_FOUND";

        }
    }

    class MainServer {
        private $db_store;
    
        public function __construct() {
            $this->db_store = new MainStore();
        }
    
        public function handleRequest() {
            // Get command and parameters from the URL
            $command = isset($_GET['command']) ? $_GET['command'] : '';
            $key = isset($_GET['key']) ? $_GET['key'] : '';
            $value = isset($_GET['value']) ? $_GET['value'] : '';
            $startKey = isset($_GET['startKey']) ? $_GET['startKey'] : '';
            $endKey = isset($_GET['endKey']) ? $_GET['endKey'] : '';
            $keys = isset($_GET['keys']) ? explode(',', $_GET['keys']) : [];
            $values = isset($_GET['values']) ? explode(',', $_GET['values']) : [];
    
            // Handle different commands
            switch (strtolower($command)) {
                case 'put':
                    if ($key && $value) {
                        $response = $this->db_store->put($key, $value);
                    } else {
                        $response = "ERROR: Invalid key or value.";
                    }
                    break;
                case 'read':
                    if ($key) {
                        $response = $this->db_store->read($key);
                    } else {
                        $response = "ERROR: Invalid key.";
                    }
                    break;
                case 'readkeyrange':
                    if ($startKey && $endKey) {
                        $response = json_encode($this->db_store->readKeyRange($startKey, $endKey));
                    } else {
                        $response = "ERROR: Invalid start or end key.";
                    }
                    break;
                case 'batchput':
                    if (count($keys) === count($values)) {
                        $response = $this->db_store->batchPut($keys, $values);
                    } else {
                        $response = "ERROR: Keys and values count mismatch.";
                    }
                    break;
                case 'delete':
                    if ($key) {
                        $response = $this->db_store->delete($key);
                    } else {
                        $response = "ERROR: Invalid key.";
                    }
                    break;
                default:
                    $response = "ERROR: Unknown command.";
                    break;
            }
    
            // Send response back to client
            header('Content-Type: application/json');
            echo json_encode(['response' => $response]);
        }
    }

    $storage = new MainServer();
    $storage->handleRequest();
