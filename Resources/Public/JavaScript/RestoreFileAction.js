document.addEventListener('click', function (event) {
  const trigger = event.target.closest('[data-restorefileaction-url]');

  if (!trigger) {
    return;
  }

  // Submitted as a detached form appended to <body>, deliberately outside
  // EXT:filelist's own page-level <form name="fileListForm">: nesting a
  // <form> inside it is invalid HTML and browsers silently drop the inner
  // tag, so this must never live inside that form's DOM subtree.
  const form = document.createElement('form');
  form.method = 'post';
  form.action = trigger.dataset.restorefileactionUrl;
  form.style.display = 'none';

  const fileUidField = document.createElement('input');
  fileUidField.type = 'hidden';
  fileUidField.name = 'fileUid';
  fileUidField.value = trigger.dataset.restorefileactionUid;
  form.appendChild(fileUidField);

  const formTokenField = document.createElement('input');
  formTokenField.type = 'hidden';
  formTokenField.name = 'formToken';
  formTokenField.value = trigger.dataset.restorefileactionToken;
  form.appendChild(formTokenField);

  document.body.appendChild(form);
  form.submit();
});
