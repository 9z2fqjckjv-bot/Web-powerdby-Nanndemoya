<?php
$pagesDir = __DIR__ . '/data/pages';
if (!is_dir($pagesDir)) {
    mkdir($pagesDir, 0777, true);
}

$messages = [];
$errors = [];

function sanitizePageName(string $name): ?string
{
    if ($name === '') {
        return null;
    }
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
        return null;
    }
    return strtolower($name);
}

function loadPageContent(string $path): string
{
    return file_exists($path) ? file_get_contents($path) : '';
}

$availablePages = [];
$dir = new DirectoryIterator($pagesDir);
foreach ($dir as $fileInfo) {
    if ($fileInfo->isFile() && $fileInfo->getExtension() === 'html') {
        $availablePages[] = $fileInfo->getBasename('.html');
    }
}
sort($availablePages);

$currentPage = $_GET['page'] ?? ($availablePages[0] ?? '');
$currentPage = sanitizePageName($currentPage) ?? ($availablePages[0] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $page = sanitizePageName($_POST['page'] ?? '');
        if (!$page) {
            $errors[] = 'ページ名の取得に失敗しました。';
        } else {
            $filename = "$pagesDir/{$page}.html";
            $content = $_POST['content'] ?? '';
            if (file_put_contents($filename, $content) !== false) {
                if (!in_array($page, $availablePages, true)) {
                    $availablePages[] = $page;
                    sort($availablePages);
                }
                $currentPage = $page;
                $messages[] = 'ページを保存しました。';
            } else {
                $errors[] = 'ページの保存に失敗しました。権限を確認してください。';
            }
        }
    }

    if ($action === 'create') {
        $newPage = sanitizePageName($_POST['new_page'] ?? '');
        if (!$newPage) {
            $errors[] = 'ページ名は英数字、ハイフン、アンダースコアで入力してください。';
        } else {
            $newFile = "$pagesDir/{$newPage}.html";
            if (file_exists($newFile)) {
                $errors[] = '同じ名前のページが既に存在します。';
            } else {
                $template = "<!DOCTYPE html>\n<html lang=\"ja\">\n<head>\n    <meta charset=\"UTF-8\">\n    <title>" . htmlspecialchars($newPage, ENT_QUOTES, 'UTF-8') . "</title>\n</head>\n<body>\n    <h1>新しいページ: " . htmlspecialchars($newPage, ENT_QUOTES, 'UTF-8') . "</h1>\n    <p>ここにコンテンツを追加しましょう。</p>\n</body>\n</html>";
                if (file_put_contents($newFile, $template) !== false) {
                    $availablePages[] = $newPage;
                    sort($availablePages);
                    $currentPage = $newPage;
                    $messages[] = '新しいページを作成しました。';
                } else {
                    $errors[] = 'ページの作成に失敗しました。';
                }
            }
        }
    }
}

if ($currentPage && !in_array($currentPage, $availablePages, true)) {
    $availablePages[] = $currentPage;
    sort($availablePages);
}

$currentContent = $currentPage ? loadPageContent("$pagesDir/{$currentPage}.html") : '';
?><!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webサイト編集アプリ</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="app-container">
        <header>
            <h1>Webサイト編集アプリ</h1>
            <p>ページを選択してHTMLを編集・保存すると、右側のプレビューで変更を確認できます。</p>
        </header>

        <div class="card-grid">
            <aside class="card">
                <h2>ページ一覧</h2>
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="alert success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <?php foreach ($errors as $error): ?>
                        <div class="alert error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <ul class="page-list">
                    <?php foreach ($availablePages as $page): ?>
                        <li>
                            <a class="page-link<?php echo $page === $currentPage ? ' active' : ''; ?>" href="?page=<?php echo urlencode($page); ?>">
                                <span><?php echo htmlspecialchars($page, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($page === $currentPage): ?>
                                    <span class="badge">編集中</span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($availablePages)): ?>
                        <li>まだページがありません。新規作成してください。</li>
                    <?php endif; ?>
                </ul>

                <hr style="margin: 24px 0; border: none; border-top: 1px solid rgba(148, 163, 184, 0.24);">

                <form id="create-form" method="post" class="create-form">
                    <input type="hidden" name="action" value="create">
                    <label for="page-name">新しいページを作成</label>
                    <div class="actions" style="margin-top: 12px;">
                        <input type="text" id="page-name" name="new_page" placeholder="例: about" required>
                        <input type="submit" value="作成">
                    </div>
                </form>
            </aside>

            <section class="card">
                <?php if ($currentPage): ?>
                    <h2>編集中: <?php echo htmlspecialchars($currentPage, ENT_QUOTES, 'UTF-8'); ?>.html</h2>
                    <form method="post" class="editor-form">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="page" value="<?php echo htmlspecialchars($currentPage, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="editor-area">
                            <textarea id="editor" name="content" spellcheck="false"><?php echo htmlspecialchars($currentContent, ENT_NOQUOTES, 'UTF-8'); ?></textarea>
                            <div class="preview">
                                <iframe id="preview-frame" title="プレビュー"></iframe>
                            </div>
                        </div>
                        <div class="actions">
                            <input type="submit" value="ページを保存">
                            <button type="button" id="reset-button">変更を取り消す</button>
                        </div>
                    </form>
                <?php else: ?>
                    <h2>ページを作成しましょう</h2>
                    <p>左側から新しいページを作成すると、ここで編集できるようになります。</p>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <script src="assets/app.js"></script>
</body>
</html>
