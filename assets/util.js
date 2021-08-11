export const MILLISECONDS = 0;
export const SECONDS = 1;

export class Time {
    static now(unit = MILLISECONDS) {
        const time = new Date().getTime();

        switch(unit) {
            case MILLISECONDS:
                return time;
            case SECONDS:
                return time / 1000;
            default:
                throw new Error(`Unknown unit`);
        }
    }
}

export const UPPERCASE_AND_DIGITS = `ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789`;

export class StringHelper {
    static random(length, set = UPPERCASE_AND_DIGITS) {
        let result = ``;
        for(let i = 0; i < length; i++) {
            result += set.charAt(Math.floor(Math.random() * set.length));
        }

        return result;
    }

    static formatPrice(floatPrice) {
        const price = floatPrice || 0;
        const priceStr = price.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        return `${priceStr}`;
    }
}

export class URL {
    static getRequestQuery() {
        const searchSplit = (location.search.substring(1, location.search.length) || '').split('&');
        const res = {};
        for (let i = 0; i < searchSplit.length; i += 1) {
            const [name, value] = searchSplit[i].split('=');
            if (name) {
                res[decodeURIComponent(name).toLowerCase()] = decodeURIComponent(value);
            }
        }

        return res;
    }

    static createRequestQuery(queryParams = {}) {
        const queryParamStr = Object
            .keys(queryParams)
            .map((key) => `${encodeURIComponent(key)}=${queryParams[key] ? encodeURIComponent(queryParams[key]) : ''}`)
            .join('&')

        return `${window.location.protocol}//${window.location.host}${window.location.pathname}${queryParamStr ? ('?' + queryParamStr) : ''}`;
    }

    static pushState(title, url) {
        window.history.pushState({path: url}, title, url);
    }

    static replaceState(title, url) {
        window.history.replaceState({path: url}, title, url);
    }
}

FormData.prototype.asObject = function() {
    const object = {};
    this.forEach((value, key) => {
        object[key] = value;
    });

    return object;
}

FormData.fromObject = function(object) {
    const data = new FormData();
    for(const [key, value] of Object.entries(object)) {
        data.append(key, value);
    }

    return data;
}

export class DateTools {
    static manageDateLimits(from, to, days) {
        $(`${from}, ${to}`).on(`change`, function () {
            const $to = $(this).parent().siblings(`.dates`).first().find(`${to}`);
            const $from = $(this).parent().siblings(`.dates`).first().find(`${from}`);

            if ($(this).hasClass(`from`)) {
                let date = new Date($(this).val());
                date.setDate(date.getDate() + days);
                let max = date
                    .getFullYear() + "-" + ('0' + (date
                    .getMonth() + 1))
                    .slice(-2) + "-" + ('0' + date
                    .getDate())
                    .slice(-2);

                $to.attr('min', $(this).val());
                $to.attr('max', max);
            } else {
                $from.attr('max', $(this).val());
            }
        });
    }
}
