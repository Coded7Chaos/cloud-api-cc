import axios from 'axios';

/*
 * Cliente HTTP del panel. Apunta a /api (mismo origen que Laravel).
 *
 * withCredentials + withXSRFToken hacen que axios mande la cookie de sesión y
 * lea la cookie XSRF-TOKEN para reenviarla como cabecera X-XSRF-TOKEN, que es
 * lo que valida el middleware CSRF de Laravel. La cookie XSRF-TOKEN la deja el
 * propio Laravel al servir el HTML del SPA.
 */
export const api = axios.create({
    baseURL: '/api',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});
