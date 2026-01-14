<template>
  <div class="col-md-2">
    <div class="img-avatar" @click="showAvatarModal">
      <div class="img-avatar_name" v-if="!avatar">
        {{ name.substring(0, 1) }}
      </div>
      <div v-else>
        <img :src="$options.filters.safeUrl(avatar)" alt="" class="main-profile-img" />
      </div>
      <div class="img-avatar_overlay">
        <div class="img-avatar_overlay-text">Update</div>
      </div>
    </div>
    <modal name="avatar-file" height="auto" class="avatar-modal" :click-to-close="false">
      <div class="p-3 bg-white shadow rounded-lg">
        <button class="btn btn-sm float-right" @click="closeAvatarModal">❌</button>
        <input ref="fileInput" type="file" name="avatar" id="file" accept="image/*" @change="setImage" />

        <!-- Cropper container -->
        <div v-if="imageSrc" class="my-3 d-flex align-items-center justify-content-center mx-auto">
          <vue-cropper class="mr-2 w-50" ref="cropper" :guides="true" :src="imageSrc" :aspect-ratio="0.9"></vue-cropper>

          <!-- Cropped image previewer -->
          <!-- Cropped image data URL (user-selected); remains unsanitized intentionally. -->
          <img class="ml-2 w-50 bg-light" :src="croppedImageSrc" />
        </div>

        <button class="btn panel-btn_close" v-if="imageSrc" @click="cropImage">Crop</button>
        <button class="btn panel-btn_save" v-if="croppedImageSrc" @click="uploadImage()" :disabled="isUploading">
          Upload
        </button>
      </div>
    </modal>
  </div>
</template>

<script>
import VueCropper from 'vue-cropperjs';
import { mapMutations } from 'vuex';

const MAX_AVATAR_BYTES = 700 * 1024; // mirrors backend max:700 rule
const UPLOAD_LOADER_MESSAGE = 'Please Wait Avatar Uploading';

// Convert a File object into a data URL preview for the cropper
function readAsDataUrl(file) {
  if (typeof FileReader !== 'function') {
    return Promise.reject(new Error('FileReader API not supported'));
  }

  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = (event) => resolve(event.target?.result || '');
    reader.onerror = () => reject(reader.error || new Error('Error reading file'));
    reader.readAsDataURL(file);
  });
}

// Mirror backend validation rules so users get instant feedback
function validateImageFile(file) {
  if (!file.type.startsWith('image/')) {
    return 'Please select an image file';
  }

  if (file.size > MAX_AVATAR_BYTES) {
    return 'Image must be 700KB or smaller';
  }

  return null;
}

// Wrap Canvas#toBlob inside a promise so we can await it
function canvasToBlob(canvas) {
  return new Promise((resolve) => canvas.toBlob(resolve));
}

export default {
  components: { VueCropper },
  props: {
    userId: { type: [Number, String], required: true },
    name: { type: String, required: true },
    avatar: { type: String, default: '' },
  },

  data() {
    return {
      imageSrc: '',
      croppedImageSrc: '',
      isUploading: false,
      objectUrl: null,
    };
  },
  beforeDestroy() {
    // Ensure temporary object URLs are released when the component unmounts
    this.resetFileInput();
  },
  methods: {
    ...mapMutations('profile', ['updateUserAvatar']),
    showAvatarModal() {
      this.imageSrc = this.avatar;
      this.$modal.show('avatar-file');
    },
    closeAvatarModal() {
      this.$modal.hide('avatar-file');
      this.resetImageState();
    },
    resetImageState() {
      // Clear preview and any temporary state, revoke object URL if used
      this.imageSrc = '';
      this.croppedImageSrc = '';
      this.resetFileInput();
    },
    async setImage(event) {
      const [file] = event.target.files || [];
      if (!file) {
        return;
      }

      // Explicitly reject SVGs on the client to avoid obvious vector-injection cases
      if (file.type === 'image/svg+xml') {
        this.$vToastify.warning('SVG uploads are not allowed');
        this.resetFileInput(event);
        return;
      }

      const validationError = validateImageFile(file);
      if (validationError) {
        this.$vToastify.warning(validationError);
        this.resetFileInput(event);
        return;
      }

      await this.loadImageIntoCropper(file);
    },

    resetFileInput(event) {
      // Clear the file input element and revoke any object URL
      if (event?.target) {
        try {
          event.target.value = '';
        } catch {
          /* ignore */
        }
      }

      if (this.$refs.fileInput) {
        try {
          this.$refs.fileInput.value = '';
        } catch {
          /* ignore */
        }
      }

      if (this.objectUrl) {
        try {
          URL.revokeObjectURL(this.objectUrl);
        } catch {
          /* ignore */
        }
        this.objectUrl = null;
      }
    },

    // Reads the file and refreshes the cropper preview
    async loadImageIntoCropper(file) {
      try {
        // Prefer object URL for preview (less memory than base64 data URLs)
        if (typeof URL !== 'undefined' && typeof URL.createObjectURL === 'function') {
          if (this.objectUrl) {
            try {
              URL.revokeObjectURL(this.objectUrl);
            } catch {
              /* ignore */
            }
          }

          this.objectUrl = URL.createObjectURL(file);
          this.applyImageSource(this.objectUrl);
          this.croppedImageSrc = '';
          return;
        }

        const imageSrc = await readAsDataUrl(file);
        this.applyImageSource(imageSrc);
        this.croppedImageSrc = '';
      } catch (error) {
        this.$vToastify.error(error?.message || 'Error reading file');
      }
    },

    applyImageSource(imageSrc) {
      this.imageSrc = imageSrc;
      if (this.$refs.cropper) {
        this.$refs.cropper.replace(imageSrc);
      }
    },

    cropImage() {
      const cropper = this.$refs.cropper;
      if (!cropper) {
        this.$vToastify.warning('No image selected');
        return;
      }

      try {
        this.croppedImageSrc = cropper.getCroppedCanvas().toDataURL();
      } catch (error) {
        this.$vToastify.error(error?.message || 'Failed to crop image');
      }
    },

    // Turns the cropped canvas into a blob and posts it to the server
    async uploadImage() {
      this.$vToastify.loader(UPLOAD_LOADER_MESSAGE);
      const canvas = this.getCropperCanvas();

      if (!canvas) {
        this.$vToastify.stopLoader();
        return;
      }

      this.isUploading = true;

      try {
        const blob = await canvasToBlob(canvas);
        if (!blob) {
          this.$vToastify.error('Failed to generate image blob');
          return;
        }

        await this.persistAvatar(blob);
      } finally {
        this.$vToastify.stopLoader();
        this.isUploading = false;
      }
    },

    getCropperCanvas() {
      if (!this.$refs.cropper) {
        this.$vToastify.warning('No image selected');
        return null;
      }

      const canvas = this.$refs.cropper.getCroppedCanvas();
      if (!canvas) {
        this.$vToastify.error('Unable to read cropped image');
        return null;
      }

      return canvas;
    },

    // Persist the final avatar blob and sync Vuex state when successful
    async persistAvatar(blob) {
      const formData = new FormData();
      formData.append('avatar', blob, 'avatar.png');

      try {
        const response = await axios.post('/users/' + this.userId + '/avatar', formData);
        this.updateUserAvatar(response.data.avatar);
        this.$vToastify.success('Avatar Updated Successfully');
        this.closeAvatarModal();
      } catch (error) {
        const msg = error?.response?.data?.message || error?.message || 'Failed To Update Avatar';
        this.$vToastify.warning(msg);
      }
    },
  },
};
</script>
