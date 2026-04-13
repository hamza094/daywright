import Vue from 'vue';

function toast() {
  return Vue.prototype.$vToastify;
}

export function toastSuccess(message) {
  toast()?.success(message);
}

export function toastInfo(message) {
  toast()?.info(message);
}
