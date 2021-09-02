import sortable from '../node_modules/html5sortable/dist/html5sortable.es.js';
import $ from 'jquery';

export default class Sortable {
    static create(selector, config) {
        const sortables = sortable(selector, config);

        //fix a bug in which sorting triggers the event but doesn't
        //actually move the item in the new container
        for(const sortable of sortables) {
            sortable.addEventListener(`sortupdate`, event => {
                const $item = $(event.detail.item);
                const $destination = $(event.detail.destination.container);

                $item.detach();
                $destination.append($item);
            })
        }

        return sortables;
    }
}
