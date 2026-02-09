// Centralized helpers for feature flags on the frontend.
// Keeps feature checks in one place and defaults unknown flags to false.

export function getFeaturesFromStore(store) {
  return store?.state?.currentUser?.user?.features ?? {};
}

export function hasFeature(store, key) {
  const features = getFeaturesFromStore(store);
  return Boolean(features?.[key]);
}

export default { getFeaturesFromStore, hasFeature };
