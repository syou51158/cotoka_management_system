# COTOKAシステム ローカル開発環境セットアップ

## 開発環境のセットアップ手順

### 1. XAMPPの設定

1. XAMPPコントロールパネルを開く
2. ApacheとMySQLを起動
   - Apache (ポート80/443)
   - MySQL (ポート3306)
3. ポート競合がある場合は設定を変更

### 2. データベースのセットアップ

1. ブラウザで次のURLにアクセス:
   ```
   http://localhost/cotoka_management_system/setup_database.php
   ```
2. 画面の指示に従ってデータベースをセットアップ
3. 初期管理者アカウント:
   - ユーザーID: admin
   - パスワード: admin123

### 3. デバッグツールの利用

1. ブラウザで次のURLにアクセスして環境診断:
   ```
   http://localhost/cotoka_management_system/debug.php
   ```
2. データベース接続エラーがある場合:
   - `config.php`の設定を確認
   - MySQLが起動しているか確認
   - ユーザー名とパスワードが正しいか確認

### 4. セッション問題の解決

セッション関連のエラーが発生した場合:
1. XAMPPのPHP設定(`php.ini`)で`session.save_path`が正しく設定されているか確認
2. `session_start()`がページの先頭で呼び出されているか確認

## 開発ワークフロー

### 1. 変更の計画

1. 実装する機能や修正を明確にする
2. 必要なファイルとデータベース変更を特定

### 2. ローカル開発

1. 変更を実装
2. `debug.php`を使ってエラーを確認
3. 変更テスト:
   - http://localhost/cotoka_management_system/ にアクセス
   - 管理者アカウントでログイン

### 3. コードの構造化とリファクタリング

1. ビジネスロジックは`controllers/`ディレクトリに配置
2. データベース関連処理は`classes/`ディレクトリに配置
3. 共通関数は`includes/functions.php`に配置

### 4. データベースマイグレーション作成

1. 新しいテーブルやカラムを追加する場合:
   - `database/migrations/`ディレクトリに新しいマイグレーションファイルを作成
   - 命名規則: `YYYYMMDD_description.sql`

## トラブルシューティング

### データベース接続エラー

- XAMPPのMySQLサービスが起動しているか確認
- `config.php`の接続情報が正しいか確認

### 画面が表示されない/白画面

- XAMPPのApacheログを確認: `C:\xampp\apache\logs\error.log`
- PHPエラーログを確認: `C:\xampp\php\logs\php_error.log`
- `debug.php`を使用してデバッグ情報を確認

### セッションエラー

- Cookieが有効になっているか確認
- `session_start()`が適切に呼び出されているか確認

## 参考ファイル

- `config.php`: データベース接続設定
- `debug.php`: 環境診断ツール
- `setup_database.php`: データベースセットアップツール 