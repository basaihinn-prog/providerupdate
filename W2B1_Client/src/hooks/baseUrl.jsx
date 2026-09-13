const configuredBaseUrl = import.meta.env.VITE_API_BASE_URL?.trim();

const BASE_URL = (configuredBaseUrl || `${window.location.origin}/api`).replace(/\/$/, '');

export default BASE_URL;
