<?php

class SupabaseClient {
    private $url;
    private $key;
    private $headers;
    
    public function __construct() {
        $this->url = SUPABASE_URL;
        $this->key = SUPABASE_ANON_KEY;
        $this->headers = [
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];
    }
    
    /**
     * Execute a SELECT query
     */
    public function select($table, $columns = '*', $conditions = [], $limit = null) {
        // Extract schema and table name
        $schema = 'public';
        $tableName = $table;
        if (strpos($table, '.') !== false) {
            list($schema, $tableName) = explode('.', $table, 2);
        }
        
        $url = $this->url . '/rest/v1/' . $tableName;
        
        $params = [];
        if ($columns !== '*') {
            $params['select'] = $columns;
        }
        
        if (is_array($conditions)) {
            foreach ($conditions as $key => $value) {
                $params[$key] = 'eq.' . $value;
            }
        }
        
        if ($limit) {
            $params['limit'] = $limit;
        }
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        // Add schema header for non-public schemas
        $headers = $this->headers;
        if ($schema !== 'public') {
            $headers[] = 'Accept-Profile: ' . $schema;
        }
        
        return $this->makeRequest('GET', $url, null, $headers);
    }
    
    /**
     * Execute an INSERT query
     */
    public function insert($table, $data) {
        // Extract schema and table name
        $schema = 'public';
        $tableName = $table;
        if (strpos($table, '.') !== false) {
            list($schema, $tableName) = explode('.', $table, 2);
        }
        
        $url = $this->url . '/rest/v1/' . $tableName;
        
        // Add schema header for non-public schemas
        $headers = $this->headers;
        if ($schema !== 'public') {
            $headers[] = 'Content-Profile: ' . $schema;
        }
        
        return $this->makeRequest('POST', $url, $data, $headers);
    }
    
    /**
     * Execute an UPDATE query
     */
    public function update($table, $data, $conditions = []) {
        // Extract schema and table name
        $schema = 'public';
        $tableName = $table;
        if (strpos($table, '.') !== false) {
            list($schema, $tableName) = explode('.', $table, 2);
        }
        
        $url = $this->url . '/rest/v1/' . $tableName;
        
        $params = [];
        foreach ($conditions as $key => $value) {
            $params[$key] = 'eq.' . $value;
        }
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        // Add schema header for non-public schemas
        $headers = $this->headers;
        if ($schema !== 'public') {
            $headers[] = 'Content-Profile: ' . $schema;
        }
        
        return $this->makeRequest('PATCH', $url, $data, $headers);
    }
    
    /**
     * Execute a DELETE query
     */
    public function delete($table, $conditions = []) {
        $url = $this->url . '/rest/v1/' . $table;
        
        $params = [];
        foreach ($conditions as $key => $value) {
            $params[$key] = 'eq.' . $value;
        }
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        // Add schema header for non-public schemas
        $headers = $this->headers;
        if (strpos($table, '.') === false) {
            $headers[] = 'Accept-Profile: cotoka';
        }
        
        return $this->makeRequest('DELETE', $url, null, $headers);
    }
    
    /**
     * Execute raw SQL query (requires RPC function)
     */
    public function rpc($function_name, $params = []) {
        $url = $this->url . '/rest/v1/rpc/' . $function_name;
        return $this->makeRequest('POST', $url, $params);
    }
    
    /**
     * Make HTTP request to Supabase
     */
    private function makeRequest($method, $url, $data = null, $headers = null) {
        $ch = curl_init();
        
        $requestHeaders = $headers ? $headers : $this->headers;
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        if ($data && in_array($method, ['POST', 'PATCH', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('cURL Error: ' . $error);
        }
        
        if ($httpCode >= 400) {
            $errorData = json_decode($response, true);
            $errorMessage = isset($errorData['message']) ? $errorData['message'] : 'HTTP Error ' . $httpCode;
            throw new Exception('Supabase API Error: ' . $errorMessage);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Test connection to Supabase
     */
    public function testConnection() {
        try {
            // Use direct SQL query via MCP instead of REST API for schema access
            // This is a simple connectivity test
            $testUrl = $this->url . '/rest/v1/';
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $testUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $this->headers,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 10
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                return [
                    'success' => true,
                    'message' => 'Supabase API connection successful'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'HTTP Error: ' . $httpCode
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}