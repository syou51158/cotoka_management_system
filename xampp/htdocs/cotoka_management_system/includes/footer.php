<?php if (isset($_SESSION["user_id"])): ?>
        </div><!-- /.main-container -->
    </div><!-- /.main-content -->
</div><!-- /.sidebar-layout -->
<?php else: ?>
    </div><!-- /.container -->
</div><!-- /.auth-background -->
<?php endif; ?>

<!-- デバッグ情報 -->
<?php if (isset($_GET['debug']) && $_GET['debug'] == 1): ?>
<div class="debug-info" style="position: fixed; bottom: 10px; right: 10px; background: rgba(0,0,0,0.7); color: #fff; padding: 10px; border-radius: 5px; font-size: 12px; z-index: 9999;">
    <p>CSSチェック: <?php echo file_exists($_SERVER['DOCUMENT_ROOT'] . '/cotoka_management_system/assets/css/styles.css') ? 'OK' : 'NG'; ?></p>
    <p>JSチェック: <?php echo file_exists($_SERVER['DOCUMENT_ROOT'] . '/cotoka_management_system/assets/js/scripts.js') ? 'OK' : 'NG'; ?></p>
    <p>SESSION: <?php echo isset($_SESSION['user_id']) ? 'ユーザーID: ' . $_SESSION['user_id'] : 'セッションなし'; ?></p>
    <p>PATH: <?php echo $_SERVER['DOCUMENT_ROOT']; ?></p>
    <p>URL: <?php echo $_SERVER['REQUEST_URI']; ?></p>
</div>
<?php endif; ?>

<!-- ダッシュボード以外のページで使用する共通スクリプト -->
<?php if (basename($_SERVER['PHP_SELF']) !== 'dashboard.php'): ?>
<!-- 存在しないスクリプト参照を削除 -->
<?php endif; ?>

<?php if (basename($_SERVER['PHP_SELF']) === 'dashboard.php'): ?>
<!-- デバッグ用スクリプト - 本番環境では無効化してください -->
<script src="assets/js/debug.js"></script>
<?php endif; ?>

<!-- ページ固有のJSファイルを読み込み -->
<?php if (isset($extra_js) && is_array($extra_js)): ?>
    <?php foreach ($extra_js as $js_file): ?>
    <script src="<?php echo $js_file; ?>" defer></script>
    <?php endforeach; ?>
<?php endif; ?>

<!-- ページ固有のインラインJSがあれば読み込む -->
<?php if (isset($page_js)) echo $page_js; ?>

<!-- パフォーマンス計測 -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.performance && window.performance.timing) {
        const perfData = window.performance.timing;
        const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
        console.log('ページ読み込み時間:', pageLoadTime, 'ms');
    }
});
</script>

</body>
</html>
