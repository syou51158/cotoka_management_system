<?php
/**
 * Database クラス
 * 
 * データベース接続とクエリ実行を管理するクラス
 */
class Database
{
    private static $instance = null;
    private $pdo;
    
    /**
     * コンストラクタ - データベース接続を確立
     */
    public function __construct()
    {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            throw new Exception('データベース接続エラー: ' . $e->getMessage());
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
     * プリペアドステートメントを作成
     * 
     * @param string $sql SQL文
     * @return PDOStatement
     */
    public function prepare($sql)
    {
        return $this->pdo->prepare($sql);
    }
    
    /**
     * クエリを実行し、結果を取得
     * 
     * @param string $sql SQL文
     * @param array $params パラメータ配列
     * @return array 結果の配列
     */
    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception('クエリ実行エラー: ' . $e->getMessage());
        }
    }
    
    /**
     * 単一行を取得
     * 
     * @param string $sql SQL文
     * @param array $params パラメータ配列
     * @return array|false 結果の配列または false
     */
    public function fetchOne($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * 全ての行を取得
     * 
     * @param string $sql SQL文
     * @param array $params パラメータ配列
     * @return array 結果の配列
     */
    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * 最後に挿入されたIDを取得
     * 
     * @return string 最後に挿入されたID
     */
    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * トランザクションを開始
     */
    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * トランザクションをコミット
     */
    public function commit()
    {
        return $this->pdo->commit();
    }
    
    /**
     * トランザクションをロールバック
     */
    public function rollBack()
    {
        return $this->pdo->rollBack();
    }
    
    /**
     * データベース接続を閉じる（互換性のため）
     * 
     * PDOは明示的にcloseする必要はないが、MySQLi互換性のために実装
     */
    public function close()
    {
        // PDOは明示的にcloseする必要がないため、何もしない
        // 変数に null を設定することでPDOオブジェクトを解放
        $this->pdo = null;
    }
    
    /**
     * PDO接続オブジェクトを取得
     * 
     * @return PDO 接続オブジェクト
     */
    public function getConnection()
    {
        return $this->pdo;
    }
} 