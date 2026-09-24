import { submitRestore } from './RestoreFileAction.js';

export default {
  restoreFile: function (table, uid, dataset) {
    submitRestore(dataset.restorefileactionUrl, dataset.restorefileactionUid, dataset.restorefileactionToken);
  },
};
