<?php
$pagesDir = __DIR__ . '/data/pages';
if (!is_dir($pagesDir)) {
    mkdir($pagesDir, 0777, true);
}

$messages = [];
$errors = [];
$aiReports = [];

$feedbackFile = __DIR__ . '/data/feedback.json';

function loadFeedbackLog(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }

    $json = file_get_contents($file);
    if ($json === false) {
        return [];
    }

    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function saveFeedbackLog(string $file, array $entries): bool
{
    $json = json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    return file_put_contents($file, $json) !== false;
}

function insertBeforeClosingTag(string $html, string $tag, string $insertion): string
{
    $position = stripos($html, $tag);
    if ($position === false) {
        return $html . $insertion;
    }

    return substr($html, 0, $position) . $insertion . substr($html, $position);
}

function generateAiSuggestions(string $feedbackText): array
{
    $suggestions = [];
    $lower = mb_strtolower($feedbackText, 'UTF-8');

    if (preg_match('/cta|call to action|申し込|購入|ボタン/u', $lower)) {
        $suggestions[] = [
            'type' => 'append_cta',
            'title' => 'CTAセクションの追加',
            'description' => '明確な行動喚起が不足しているため、CTAセクションを追加します。'
        ];
    }

    if (preg_match('/レビュー|声|testimonial|実績|信頼/u', $lower)) {
        $suggestions[] = [
            'type' => 'append_testimonials',
            'title' => 'お客様の声セクションの追加',
            'description' => '信頼性向上のため、お客様の声セクションを提案します。'
        ];
    }

    if (preg_match('/読み込|速度|高速|パフォーマンス|軽く/u', $lower)) {
        $suggestions[] = [
            'type' => 'performance_note',
            'title' => 'パフォーマンス改善の検討',
            'description' => '画像の最適化や不要なスクリプトの削除などのパフォーマンス改善を検討してください。'
        ];
    }

    if (preg_match('/アクセシビリティ|コントラスト|色弱|読みづら/u', $lower)) {
        $suggestions[] = [
            'type' => 'accessibility_note',
            'title' => 'アクセシビリティ向上の検討',
            'description' => 'コントラスト比の改善やARIA属性の追加などアクセシビリティ向上を検討してください。'
        ];
    }

    return $suggestions;
}

function applyAiSuggestion(array $suggestion, string $pagePath): array
{
    if (!file_exists($pagePath)) {
        return ['status' => 'error', 'message' => '対象のページファイルが見つかりませんでした。'];
    }

    $content = file_get_contents($pagePath);
    if ($content === false) {
        return ['status' => 'error', 'message' => 'ページ内容の読み込みに失敗しました。'];
    }

    switch ($suggestion['type']) {
        case 'append_cta':
            if (strpos($content, 'data-ai="cta-block"') !== false) {
                return ['status' => 'skipped', 'message' => 'CTAセクションは既に存在するためスキップしました。'];
            }

            $ctaBlock = "\n    <!-- AI-generated improvement: CTA block -->\n    <section class=\"ai-section ai-cta\" data-ai=\"cta-block\">\n        <h2>気に入っていただけましたか？</h2>\n        <p>今すぐお問い合わせいただき、詳細をご確認ください。担当チームがサポートします。</p>\n        <a class=\"ai-button\" href=\"#contact\">無料相談を申し込む</a>\n    </section>\n";
            $updated = insertBeforeClosingTag($content, '</body>', $ctaBlock);
            if (file_put_contents($pagePath, $updated) === false) {
                return ['status' => 'error', 'message' => 'CTAセクションの追加に失敗しました。'];
            }

            return ['status' => 'applied', 'message' => 'CTAセクションを自動追加しました。'];

        case 'append_testimonials':
            if (strpos($content, 'data-ai="testimonial-block"') !== false) {
                return ['status' => 'skipped', 'message' => 'お客様の声セクションは既に存在するためスキップしました。'];
            }

            $testimonialBlock = "\n    <!-- AI-generated improvement: testimonials -->\n    <section class=\"ai-section ai-testimonials\" data-ai=\"testimonial-block\">\n        <h2>利用者の声</h2>\n        <div class=\"ai-testimonial-grid\">\n            <article>\n                <p>\"このサービスで作業がとてもスムーズになりました。\"</p>\n                <span>- デザイナー Aさん</span>\n            </article>\n            <article>\n                <p>\"サポートが充実していて安心して導入できました。\"</p>\n                <span>- マーケター Bさん</span>\n            </article>\n        </div>\n    </section>\n";
            $updated = insertBeforeClosingTag($content, '</body>', $testimonialBlock);
            if (file_put_contents($pagePath, $updated) === false) {
                return ['status' => 'error', 'message' => 'お客様の声セクションの追加に失敗しました。'];
            }

            return ['status' => 'applied', 'message' => 'お客様の声セクションを自動追加しました。'];

        default:
            return ['status' => 'note', 'message' => $suggestion['description'] ?? '改善案を記録しました。'];
    }
}

$feedbackLog = loadFeedbackLog($feedbackFile);

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

    if ($action === 'feedback') {
        $targetPage = sanitizePageName($_POST['feedback_page'] ?? '') ?? '';
        $feedbackText = trim($_POST['feedback_text'] ?? '');

        if ($feedbackText === '') {
            $errors[] = 'フィードバックの内容を入力してください。';
        } else {
            $entry = [
                'page' => $targetPage,
                'text' => $feedbackText,
                'created_at' => date('c')
            ];
            $feedbackLog[] = $entry;
            if (!saveFeedbackLog($feedbackFile, $feedbackLog)) {
                $errors[] = 'フィードバックの保存に失敗しました。';
            } else {
                $messages[] = 'フィードバックを受け取りました。AIが改善提案を生成します。';
            }

            $suggestions = generateAiSuggestions($feedbackText);
            if (!empty($suggestions)) {
                $appliedReports = [];
                foreach ($suggestions as $suggestion) {
                    $report = $suggestion;
                    $report['status'] = 'note';
                    $report['message'] = $suggestion['description'];

                    if ($targetPage !== '') {
                        $pagePath = "$pagesDir/{$targetPage}.html";
                        $result = applyAiSuggestion($suggestion, $pagePath);
                        $report['status'] = $result['status'];
                        $report['message'] = $result['message'];

                        if ($result['status'] === 'applied') {
                            $messages[] = $result['message'];
                        } elseif ($result['status'] === 'error') {
                            $errors[] = $result['message'];
                        }
                    }

                    $appliedReports[] = $report;
                }

                $aiReports = array_merge($aiReports, $appliedReports);
            } else {
                $aiReports[] = [
                    'title' => 'AI提案なし',
                    'description' => '該当する改善案を見つけられませんでした。より具体的なフィードバックをお試しください。',
                    'status' => 'note',
                    'message' => '改善案を見つけられませんでした。'
                ];
            }

            if ($targetPage !== '') {
                $currentPage = $targetPage;
            }
        }
    }
}

if ($currentPage && !in_array($currentPage, $availablePages, true)) {
    $availablePages[] = $currentPage;
    sort($availablePages);
}

$currentContent = $currentPage ? loadPageContent("$pagesDir/{$currentPage}.html") : '';
$recentFeedback = array_slice(array_reverse($feedbackLog), 0, 5);
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

            <section class="card ai-card">
                <h2>AI改善フィードバック</h2>
                <form id="feedback-form" method="post" class="feedback-form">
                    <input type="hidden" name="action" value="feedback">
                    <label for="feedback-page">対象ページ（任意）</label>
                    <select id="feedback-page" name="feedback_page">
                        <option value="">全体に関するフィードバック</option>
                        <?php foreach ($availablePages as $page): ?>
                            <option value="<?php echo htmlspecialchars($page, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $page === $currentPage ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($page, ENT_QUOTES, 'UTF-8'); ?>.html
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="feedback-text">フィードバック内容</label>
                    <textarea id="feedback-text" name="feedback_text" rows="5" placeholder="例: 申し込みボタンがわかりにくいので、CTAを追加してほしいです。"></textarea>

                    <div class="actions">
                        <input type="submit" value="AIに改善を依頼">
                    </div>
                </form>

                <div class="ai-report">
                    <h3>最新のAI提案</h3>
                    <?php if (!empty($aiReports)): ?>
                        <ul>
                            <?php foreach ($aiReports as $report): ?>
                                <li class="status-<?php echo htmlspecialchars($report['status'] ?? 'note', ENT_QUOTES, 'UTF-8'); ?>">
                                    <strong><?php echo htmlspecialchars($report['title'] ?? $report['message'] ?? '提案', ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <p><?php echo htmlspecialchars($report['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="muted">まだAIからの提案はありません。フォームからフィードバックを送信してください。</p>
                    <?php endif; ?>
                </div>

                <?php if (!empty($recentFeedback)): ?>
                    <div class="feedback-log">
                        <h3>最近のフィードバック</h3>
                        <ul class="feedback-list">
                            <?php foreach ($recentFeedback as $feedback): ?>
                                <li>
                                    <div class="feedback-meta">
                                        <span><?php echo htmlspecialchars($feedback['created_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if (!empty($feedback['page'])): ?>
                                            <span class="badge">対象: <?php echo htmlspecialchars($feedback['page'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <p><?php echo nl2br(htmlspecialchars($feedback['text'], ENT_QUOTES, 'UTF-8')); ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <script src="assets/app.js"></script>
</body>
</html>
