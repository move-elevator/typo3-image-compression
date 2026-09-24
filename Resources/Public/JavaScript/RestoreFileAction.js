// Submitted as a detached form appended to <body>, deliberately outside
// EXT:filelist's own page-level <form name="fileListForm">: nesting a
// <form> inside it is invalid HTML and browsers silently drop the inner
// tag, so this must never live inside that form's DOM subtree. Shared by
// the file-list dropdown button below and RestoreFileContextMenuAction.js.
export function submitRestore(url, fileUid, formToken) {
  const form = document.createElement('form');
  form.method = 'post';
  form.action = url;
  form.style.display = 'none';

  const fileUidField = document.createElement('input');
  fileUidField.type = 'hidden';
  fileUidField.name = 'fileUid';
  fileUidField.value = fileUid;
  form.appendChild(fileUidField);

  const formTokenField = document.createElement('input');
  formTokenField.type = 'hidden';
  formTokenField.name = 'formToken';
  formTokenField.value = formToken;
  form.appendChild(formTokenField);

  document.body.appendChild(form);
  form.submit();
}

document.addEventListener('click', function (event) {
  const trigger = event.target.closest('[data-restorefileaction-url]');

  if (!trigger) {
    return;
  }

  submitRestore(trigger.dataset.restorefileactionUrl, trigger.dataset.restorefileactionUid, trigger.dataset.restorefileactionToken);
});
