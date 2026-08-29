import { Controller } from '@hotwired/stimulus';

/**
 * Country -> county -> city cascade.
 *
 * The city list holds around 16 000 rows, so rendering it as a plain select
 * would ship the whole table to the browser on every page load. The field is an
 * AjaxEntityType — a hidden input carrying the id — and the visible selects are
 * filled on demand from the location endpoints.
 *
 * The company form still carries its own older copy of this logic, tangled with
 * its file handling; worth merging the two when that form is next touched.
 */
const API_BASE = '/admin/location';

export default class extends Controller {
    static targets = ['country', 'county', 'city', 'cityHidden'];

    static values = {
        // Set on edit so the cascade can be rebuilt from the stored city.
        cityId: { type: Number, default: 0 },
    };

    connect() {
        this.countryTarget.addEventListener('change', this.onCountryChange);
        this.countyTarget.addEventListener('change', this.onCountyChange);
        this.cityTarget.addEventListener('change', this.onCityChange);

        if (this.cityIdValue > 0) {
            this.restore(this.cityIdValue);
        } else {
            this.setEnabled(this.countyTarget, false);
            this.setEnabled(this.cityTarget, false);
        }
    }

    disconnect() {
        this.countryTarget.removeEventListener('change', this.onCountryChange);
        this.countyTarget.removeEventListener('change', this.onCountyChange);
        this.cityTarget.removeEventListener('change', this.onCityChange);
    }

    onCountryChange = async () => {
        this.reset(this.countyTarget);
        this.reset(this.cityTarget);
        this.cityHiddenTarget.value = '';

        const countryId = this.countryTarget.value;

        if (!countryId) {
            this.setEnabled(this.countyTarget, false);
            this.setEnabled(this.cityTarget, false);

            return;
        }

        await this.fill(this.countyTarget, `${API_BASE}/counties/${countryId}`);
        this.setEnabled(this.countyTarget, true);
    };

    onCountyChange = async () => {
        this.reset(this.cityTarget);
        this.cityHiddenTarget.value = '';

        const countyId = this.countyTarget.value;

        if (!countyId) {
            this.setEnabled(this.cityTarget, false);

            return;
        }

        await this.fill(this.cityTarget, `${API_BASE}/cities/${countyId}`);
        this.setEnabled(this.cityTarget, true);
    };

    /** The visible select is presentation; the hidden input is what gets posted. */
    onCityChange = () => {
        this.cityHiddenTarget.value = this.cityTarget.value;
    };

    /** Rebuilds the three selects from a stored city, on edit. */
    async restore(cityId) {
        try {
            const response = await fetch(`${API_BASE}/city-context/${cityId}`);

            if (!response.ok) {
                return;
            }

            const context = await response.json();

            this.countryTarget.value = context.countryId ?? '';

            await this.fill(this.countyTarget, `${API_BASE}/counties/${context.countryId}`);
            this.countyTarget.value = context.countyId ?? '';
            this.setEnabled(this.countyTarget, true);

            await this.fill(this.cityTarget, `${API_BASE}/cities/${context.countyId}`);
            this.cityTarget.value = String(cityId);
            this.setEnabled(this.cityTarget, true);
        } catch (error) {
            // A failed restore leaves the cascade empty rather than breaking the form.
        }
    }

    async fill(select, url) {
        const response = await fetch(url);

        if (!response.ok) {
            return;
        }

        for (const item of await response.json()) {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;
            select.append(option);
        }
    }

    reset(select) {
        while (select.options.length > 1) {
            select.remove(1);
        }

        select.value = '';
    }

    setEnabled(select, enabled) {
        select.disabled = !enabled;
    }
}
