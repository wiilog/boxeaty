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

export class String {
    static random(length, set = UPPERCASE_AND_DIGITS) {
        let result = ``;
        for(let i = 0; i < length; i++) {
            result += set.charAt(Math.floor(Math.random() * set.length));
        }

        return result;
    }
}

FormData.prototype.asObject = function() {
    const object = {};
    this.forEach((value, key) => {
        object[key] = value;
    });

    return object;
}