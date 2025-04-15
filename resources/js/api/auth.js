import axios from 'axios';

export const requestCode = (phone) => {
    return axios.post('/auth/request-code', { phone });
};

export const verifyCode = (phone, code) => {
    return axios.post('/auth/verify-code', { phone, code });
};
