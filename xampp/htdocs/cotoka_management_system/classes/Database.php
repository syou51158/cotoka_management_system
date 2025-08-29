<?php
/**
 * Database クラス
 * 
 * Supabase REST APIを使用したデータベース操作を管理するクラス
 */
class Database
{
    private static $instance = null;
    private $supabase_url;
    private $supabase_key;
    private $schema;
    
    /**
     * コンストラクタ - Supabase REST API接続を初期化
     */
    public function __construct()
    {
        $this->supabase_url = defined('SUPABASE_URL') ? SUPABASE_URL : '';
        // サーバー側ではサービスロールキーが定義されていればそれを優先
        if (defined('SUPABASE_SERVICE_ROLE_KEY') && !empty(SUPABASE_SERVICE_ROLE_KEY)) {
            $this->supabase_key = SUPABASE_SERVICE_ROLE_KEY;
        } else {
            $this->supabase_key = defined('SUPABASE_ANON_KEY') ? SUPABASE_ANON_KEY : '';
        }
        // 既定スキーマはcotoka（必要に応じて定数で上書き可）
        $this->schema = defined('SUPABASE_SCHEMA') && SUPABASE_SCHEMA ? SUPABASE_SCHEMA : 'cotoka';
        
        if (empty($this->supabase_url) || empty($this->supabase_key)) {
            throw new Exception('Supabase設定が不足しています。');
        }
    }
    
    /**
     * シングルトンインスタンスを取得
     * 
     * @return Database
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Supabase REST APIでクエリを実行
     * 
     * @param string $table テーブル名
     * @param array $filters フィルター条件
     * @param string $select 選択するカラム
     * @param array $options 追加オプション（order, limit, offset など）
     * @return array 結果の配列
     */
    public function select($table, $filters = [], $select = '*', $options = [])
    {
        $url = rtrim($this->supabase_url, '/') . '/rest/v1/' . $table;
        $query_params = ['select=' . urlencode($select)];
        
        foreach ($filters as $key => $value) {
            // IN句サポート: 値が配列の場合は in.(v1,v2,...) を使用
            if (is_array($value)) {
                if (empty($value)) { continue; }
                // 数値/文字列をそのままカンマ区切りで連結（PostgREST側でデコードされるため全体をURLエンコード）
                $inList = '(' . implode(',', array_map(function($v){ return (string)$v; }, $value)) . ')';
                $query_params[] = urlencode($key) . '=in.' . urlencode($inList);
            } else {
                // gte, lteなどの演算子をサポート
                list($column, $operator) = array_pad(explode('.', $key), 2, 'eq');
                $query_params[] = urlencode($column) . '=' . $operator . '.' . urlencode($value);
            }
        }
        
        // 追加オプション
        if (!empty($options)) {
            if (isset($options['order']) && $options['order']) {
                $query_params[] = 'order=' . urlencode($options['order']); // 例: name.asc
            }
            if (isset($options['limit']) && is_numeric($options['limit'])) {
                $query_params[] = 'limit=' . (int)$options['limit'];
            }
            if (isset($options['offset']) && is_numeric($options['offset'])) {
                $query_params[] = 'offset=' . (int)$options['offset'];
            }
        }
        
        if (!empty($query_params)) {
            $url .= '?' . implode('&', $query_params);
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $this->supabase_key,
            'Authorization: Bearer ' . $this->supabase_key,
            // スキーマ指定（読み取り）
            'Accept-Profile: ' . $this->schema,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception('Supabaseクエリエラー: HTTP ' . $http_code);
        }
        
        return json_decode($response, true) ?: [];
    }
    
    /**
     * 単一行を取得
     * 
     * @param string $table テーブル名
     * @param array $filters フィルター条件
     * @param string $select 選択するカラム
     * @param array $options 追加オプション
     * @return array|false 結果の配列または false
     */
    public function fetchOne($table, $filters = [], $select = '*', $options = [])
    {
        $results = $this->select($table, $filters, $select, $options);
        return !empty($results) ? $results[0] : false;
    }
    
    /**
     * 全ての行を取得
     * 
     * @param string $table テーブル名
     * @param array $filters フィルター条件
     * @param string $select 選択するカラム
     * @param array $options 追加オプション
     * @return array 結果の配列
     */
    public function fetchAll($table, $filters = [], $select = '*', $options = [])
    {
        return $this->select($table, $filters, $select, $options);
    }
    
    /**
     * データを挿入
     * 
     * @param string $table テーブル名
     * @param array $data 挿入するデータ
     * @return array 挿入結果
     */
    public function insert($table, $data)
    {
        $url = rtrim($this->supabase_url, '/') . '/rest/v1/' . $table;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $this->supabase_key,
            'Authorization: Bearer ' . $this->supabase_key,
            'Prefer: return=representation',
            // スキーマ指定（書き込み）
            'Content-Profile: ' . $this->schema,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 201) {
            throw new Exception('Supabase挿入エラー: HTTP ' . $http_code . ' - ' . $response);
        }
        
        return json_decode($response, true) ?: [];
    }
    
    /**
     * データを更新
     * 
     * @param string $table テーブル名
     * @param array $data 更新するデータ
     * @param array $filters フィルター条件
     * @return bool 更新成功かどうか
     */
    public function update($table, $data, $filters = [])
    {
        $url = rtrim($this->supabase_url, '/') . '/rest/v1/' . $table;
        $query_params = [];
        
        foreach ($filters as $key => $value) {
            $query_params[] = urlencode($key) . '=eq.' . urlencode($value);
        }
        
        if (!empty($query_params)) {
            $url .= '?' . implode('&', $query_params);
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $this->supabase_key,
            'Authorization: Bearer ' . $this->supabase_key,
            'Prefer: return=representation',
            // スキーマ指定（書き込み）
            'Content-Profile: ' . $this->schema,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $http_code === 200;
    }
    
    /**
     * データを削除
     * 
     * @param string $table テーブル名
     * @param array $filters フィルター条件
     * @return bool 削除成功かどうか
     */
    public function delete($table, $filters = [])
    {
        $url = rtrim($this->supabase_url, '/') . '/rest/v1/' . $table;
        $query_params = [];
        
        foreach ($filters as $key => $value) {
            $query_params[] = urlencode($key) . '=eq.' . urlencode($value);
        }
        
        if (!empty($query_params)) {
            $url .= '?' . implode('&', $query_params);
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $this->supabase_key,
            'Authorization: Bearer ' . $this->supabase_key,
            // スキーマ指定（書き込み）
            'Content-Profile: ' . $this->schema,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $http_code === 204;
    }
    
    /**
     * カウントクエリを実行
     * 
     * @param string $table テーブル名
     * @param array $filters フィルター条件
     * @return int カウント結果
     */
    public function count($table, $filters = [])
    {
        $url = rtrim($this->supabase_url, '/') . '/rest/v1/' . $table;
        $query_params = ['select=*'];
        
        foreach ($filters as $key => $value) {
            $query_params[] = urlencode($key) . '=eq.' . urlencode($value);
        }
        
        if (!empty($query_params)) {
            $url .= '?' . implode('&', $query_params);
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $this->supabase_key,
            'Authorization: Bearer ' . $this->supabase_key,
            'Prefer: count=exact',
            // スキーマ指定（読み取り）
            'Accept-Profile: ' . $this->schema,
        ]);
        // レスポンスヘッダも取得
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            return 0;
        }
        
        $header = substr($response, 0, $header_size);
        $count = 0;
        if (preg_match('/Content-Range:\s*\d+-\d+\/(\d+)/i', $header, $matches)) {
            $count = (int)$matches[1];
        }
        
        return $count;
    }
    
    /**
     * 生SQLクエリ実行（RPC関数呼び出し用）
     * 
     * @param string $rpcFunction RPC関数名
     * @param array $params パラメータ
     * @return array|false 結果の配列
     */
    public function rpc($rpcFunction, $params = [])
    {
        $url = rtrim($this->supabase_url, '/') . '/rest/v1/rpc/' . $rpcFunction;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $this->supabase_key,
            'Authorization: Bearer ' . $this->supabase_key,
            'Accept-Profile: ' . $this->schema,
            'Content-Profile: ' . $this->schema,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception('Supabase RPC エラー: HTTP ' . $http_code . ' - ' . $response);
        }
        
        return json_decode($response, true) ?: [];
    }
    
    /**
     * 接続を閉じる（互換性のため）
     */
    public function close()
    {
        // REST APIなので特に何もしない
    }
}