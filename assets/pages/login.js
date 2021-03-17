import '../app.js';

const LOGIN_EMAIL = `login.email`;
const LOGIN_PASSWORD = `login.password`;

$(document).ready(() => {
    const $email = $(`input[name="email"]`);
    const $password = $(`input[name="password"]`);

    const email = localStorage.getItem(LOGIN_EMAIL);
    const password = localStorage.getItem(LOGIN_PASSWORD);
    if(email || password) {
        $(`#keep-credentials`).prop(`checked`, true);
        $email.val(email);
        $password.val(password);
    }

    $(`form button[type="submit"]`).click(() => {
        if($(`#keep-credentials`).is(`:checked`)) {
            localStorage.setItem(LOGIN_EMAIL, $email.val());
            localStorage.setItem(LOGIN_PASSWORD, $password.val());
        } else {
            localStorage.removeItem(LOGIN_EMAIL);
            localStorage.removeItem(LOGIN_PASSWORD);
        }
    })
})
