import Leaflet from "leaflet";
import AJAX from "./ajax";

const FIND_COORDINATES = `https://nominatim.openstreetmap.org/search?format=json&q=13%20rue%20du%208%20mai%201945`;

export class Map {
    map;
    locations = [];

    static create(element) {
        const map = new Map();
        map.map = Leaflet.map(element).setView([46.467247, 2.960474], 5);
        Leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map.map);

        return map;
    }

    addMarker(location) {
        const existing = this.locations.find(l => l.latitude === location.latitude && l.longitude === location.longitude);
        if(existing) {
            return;
        }

        const marker = Leaflet.marker([location.latitude, location.longitude]);
        this.map.addLayer(marker);

        if (location.title) {
            location
                .bindPopup(location.title)
                .openPopup();
        }

        location.marker = marker;
        this.locations.push(location);
    }

    setMarkers(locations, fit = true) {
        for (const location of this.locations) {
            this.removeMarker(location);
        }

        for (const location of locations) {
            this.addMarker(location);
        }

        if(fit) {
            this.fitBounds();
        }
    }

    removeMarker(location) {
        this.map.removeLayer(location.marker);
        this.locations.splice(this.locations.indexOf(location), 1);
    }

    fitBounds() {
        const bounds = Leaflet.latLngBounds();
        for (const location of this.locations) {
            bounds.extend(Leaflet.latLng(location.latitude, location.longitude));
        }

        this.map.flyToBounds(bounds, {
            paddingTopLeft: [0, 30],
        });
    }
}

export async function createMap(element, markers) {
}

export async function findCoordinates(address) {
    const params = {
        format: `json`,
        q: address,
    };

    return await AJAX.url(`GET`, FIND_COORDINATES, params).json();
}