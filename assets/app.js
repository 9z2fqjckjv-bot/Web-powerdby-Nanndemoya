const editor = document.getElementById('editor');
const previewFrame = document.getElementById('preview-frame');
const pageNameInput = document.getElementById('page-name');
const createForm = document.getElementById('create-form');
const resetButton = document.getElementById('reset-button');
const feedbackForm = document.getElementById('feedback-form');
const feedbackTextarea = document.getElementById('feedback-text');

const updatePreview = () => {
    if (!editor || !previewFrame) {
        return;
    }
    const doc = previewFrame.contentDocument || previewFrame.contentWindow.document;
    doc.open();
    doc.write(editor.value);
    doc.close();
};

if (editor) {
    editor.addEventListener('input', updatePreview);
    window.addEventListener('load', updatePreview);
}

if (resetButton && editor) {
    resetButton.addEventListener('click', () => {
        editor.value = editor.defaultValue;
        updatePreview();
    });
}

if (createForm && pageNameInput) {
    createForm.addEventListener('submit', (event) => {
        const slug = pageNameInput.value.trim();
        const valid = /^[a-zA-Z0-9_-]+$/.test(slug);
        if (!valid) {
            event.preventDefault();
            alert('ページ名には英数字、ハイフン、アンダースコアのみ使用できます。');
        }
    });
}

if (feedbackForm && feedbackTextarea) {
    feedbackForm.addEventListener('submit', (event) => {
        if (feedbackTextarea.value.trim().length < 5) {
            event.preventDefault();
            alert('フィードバックは5文字以上で入力してください。');
        }
    });
}
