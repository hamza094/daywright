import { ref } from 'vue';

const FILE_CONFIG = {
  MAX_FILE_BYTES: 700 * 1024,
  ALLOWED_FILE_TYPES: [
    'image/jpeg',
    'image/png',
    'image/jpg',
    'application/pdf',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  ],
};

export function useFileUpload(toastNotification = null) {
  const file = ref(null);
  const fileName = ref('');

  const validateFile = (uploadedFile) => {
    if (!FILE_CONFIG.ALLOWED_FILE_TYPES.includes(uploadedFile.type)) {
      return { valid: false, message: 'Allowed files: JPG, PNG, PDF, DOCX' };
    }

    if (uploadedFile.size > FILE_CONFIG.MAX_FILE_BYTES) {
      return { valid: false, message: 'Attachments must be 700KB or smaller' };
    }

    return { valid: true };
  };

  const fileUpload = (event, fileInputRef) => {
    const [uploadedFile] = event.target.files;

    if (!uploadedFile) {
      return;
    }

    const validation = validateFile(uploadedFile);

    if (!validation.valid) {
      if (toastNotification) {
        toastNotification.warning(validation.message);
      }
      removeFile(fileInputRef);
      return;
    }

    file.value = uploadedFile;
    fileName.value = uploadedFile.name;
  };

  const removeFile = (fileInputRef) => {
    file.value = null;
    fileName.value = '';
    if (fileInputRef) {
      fileInputRef.value = '';
    }
  };

  return {
    file,
    fileName,
    fileUpload,
    removeFile,
  };
}
