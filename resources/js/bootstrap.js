import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Pusher SPA client bootstrap (optional: import and initialize from your app entry)
// This keeps pusher client bundled for SPA usage
// Pusher removed
