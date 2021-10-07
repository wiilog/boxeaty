import $ from "jquery";
import Flash, {ERROR} from "../flash";

$(function() {
    $(`#new-client-order`).on('click', function() {
        if($(this).data('has-default-crate')) {
            window.location.href = Routing.generate(`client_orders_list`, {action: 'new'});
        } else {
            Flash.add(ERROR, 'Un type de caisse par défaut est nécessaire afin de pouvoir créer une commande client')
        }
    });
});
