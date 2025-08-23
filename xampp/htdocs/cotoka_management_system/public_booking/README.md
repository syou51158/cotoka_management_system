# 予約URL システム使用マニュアル

## 概要

予約URLシステムは、各サロンの予約ページへのアクセスを提供するシステムです。様々な予約ソース（Instagram、Google、その他の広告など）からのアクセスを追跡するために使用されます。

## URL形式

予約URLは以下の形式で構成されます：

### ローカル環境

```
# 基本形式（最も信頼性の高い方法）
http://localhost/cotoka_management_system/public_booking/index.php?salon_id=[サロンID]&source=[ソース]

# スラッグパラメータを使った形式
http://localhost/cotoka_management_system/public_booking/index.php?slug=[サロンスラッグ]&source=[ソース]

# クリーンURL形式 (.htaccessが有効な場合)
http://localhost/cotoka_management_system/public_booking/[サロンスラッグ]?source=[ソース]
```

### 本番環境（例）

```
# 基本形式
https://[ドメイン]/public_booking/index.php?salon_id=[サロンID]&source=[ソース]

# スラッグパラメータを使った形式
https://[ドメイン]/public_booking/index.php?slug=[サロンスラッグ]&source=[ソース]

# クリーンURL形式 (.htaccessが有効な場合)
https://[ドメイン]/public_booking/[サロンスラッグ]?source=[ソース]
```

## URLパラメータの説明

- `salon_id`: サロンの一意のID（例: 1, 2, 3...）
- `slug`: サロンの一意のURL識別子（例: `demo-salon-shinjuku`, `ginza-salon`など）
- `source`: 予約ソースの識別子（例: `instagram`, `google`, `facebook`など）

## トラブルシューティング

予約URLにアクセスできない場合は、以下の点を確認してください：

1. **パスが正しいか確認**
   - ローカル環境では `/cotoka_management_system` ディレクトリを含めてください
   - 本番環境ではドメイン設定に応じてパスを調整してください

2. **スラッグが正しいか確認**
   - サロンスラッグはデータベース内の `salons` テーブルの `url_slug` 列と一致する必要があります
   - スラッグの確認は `check_slug.php` または `url_debug.php` を使用してください

3. **.htaccess設定の確認**
   - `mod_rewrite` モジュールが有効であることを確認してください
   - `AllowOverride All` がApacheの設定で有効になっていることを確認してください

4. **URLエンコーディングの問題**
   - 日本語やその他の非ASCII文字がURLに含まれる場合、エンコーディングの問題が発生することがあります
   - 可能であれば、英数字、ハイフン、アンダースコアのみを使用したスラッグを使用してください

## デバッグツール

問題が発生した場合は、以下のツールを使用してデバッグできます：

- `check_slug.php`: スラッグの検証と正しいURLの生成
- `url_debug.php`: URLの解析と問題の診断
- `test.php`: 基本的なシステム情報の表示

## 注意事項

- **本番環境ではデバッグツールを削除** または アクセス制限を設けてください
- 予約ソース管理で生成されたURLは、正しいフォーマットであることを確認してください
- URLの変更は、既存のマーケティング資料やSNSプロフィールのリンクに影響を与える可能性があります 