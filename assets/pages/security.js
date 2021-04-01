import $ from "jquery";

import '../select2';

//password toggle eye icon
$(document).on(`click`, `.show-password span`, function() {
    const $input = $(this).parents(`label`).find(`input`);

    if($input.attr(`type`) === `password`) {
        $input.attr(`type`, `text`);
    } else {
        $input.attr(`type`, `password`);
    }
})
