import './styles/app.scss';
import 'bootstrap';

import 'datatables.net';
import 'datatables.net-dt/js/dataTables.dataTables';
import '@fortawesome/fontawesome-free/js/all.js';

import $ from 'jquery';
import Routing from '../public/bundles/fosjsrouting/js/router.min.js';

global.$ = $;
global.Routing = Routing;

const routes = require('../public/generated/routes.json');
Routing.setRoutingData(routes);

$(document).ready(function() {
    $('#example').dataTable( {
        "order": [[ 3, "desc" ]]
    } );
} );
