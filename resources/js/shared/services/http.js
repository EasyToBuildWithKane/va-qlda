import axios from 'axios';

/**
 * Thin JSON API client for non-Inertia endpoints (notifications, future LT-01).
 * Session + CSRF via Laravel defaults (bootstrap sets X-XSRF-TOKEN).
 */
const client = axios.create({
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

client.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 419) {
            window.location.reload();
        }
        return Promise.reject(error);
    },
);

/**
 * @param {string} url
 * @param {import('axios').AxiosRequestConfig} [config]
 */
export async function httpGet(url, config = {}) {
    const { data } = await client.get(url, config);
    return data;
}

/**
 * @param {string} url
 * @param {object} [body]
 * @param {import('axios').AxiosRequestConfig} [config]
 */
export async function httpPost(url, body, config = {}) {
    const { data } = await client.post(url, body, config);
    return data;
}

/**
 * @param {string} url
 * @param {object} [body]
 * @param {import('axios').AxiosRequestConfig} [config]
 */
export async function httpPut(url, body, config = {}) {
    const { data } = await client.put(url, body, config);
    return data;
}

/**
 * @param {string} url
 * @param {import('axios').AxiosRequestConfig} [config]
 */
export async function httpDelete(url, config = {}) {
    const { data } = await client.delete(url, config);
    return data;
}

export { client as httpClient };
