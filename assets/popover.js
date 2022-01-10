export class Popover {
    static initImage() {
        $(`[data-toggle=popover-hover]`).popover({
            html: true,
            trigger: `hover`,
            placement: `bottom`,
            container: `body`,
            content: function () {
                return `<img src="${$(this).data('img')}" width="400"/>`;
            }
        });
    }
}
