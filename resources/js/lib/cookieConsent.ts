import 'vanilla-cookieconsent/dist/cookieconsent.css';
import './cookieConsent.css';
import * as CookieConsent from 'vanilla-cookieconsent';

let initialised = false;

export function initCookieConsent(): void {
    if (initialised) return;
    initialised = true;

    CookieConsent.run({
        guiOptions: {
            consentModal: {
                layout: 'bar inline',
                position: 'bottom',
                equalWeightButtons: false,
                flipButtons: false,
            },
            preferencesModal: {
                layout: 'box',
                position: 'right',
                equalWeightButtons: false,
                flipButtons: false,
            },
        },
        categories: {
            necessary: {
                enabled: true,
                readOnly: true,
            },
            functional: {
                enabled: false,
                readOnly: false,
            },
        },
        language: {
            default: 'en',
            translations: {
                en: {
                    consentModal: {
                        title: '',
                        description:
                            'We use essential cookies to run this site. Optional cookies (e.g. the instructor map) only load if you accept.',
                        acceptAllBtn: 'Accept all',
                        acceptNecessaryBtn: 'Reject all',
                        showPreferencesBtn: 'Manage',
                    },
                    preferencesModal: {
                        title: 'Cookie preferences',
                        acceptAllBtn: 'Accept all',
                        acceptNecessaryBtn: 'Reject all',
                        savePreferencesBtn: 'Save preferences',
                        closeIconLabel: 'Close',
                        sections: [
                            {
                                title: 'Strictly necessary',
                                description:
                                    'Essential cookies for login, security, and remembering your form progress. These cannot be switched off.',
                                linkedCategory: 'necessary',
                            },
                            {
                                title: 'Functional',
                                description:
                                    'Used to load Google Maps so you can see instructors near you. With these off the map is hidden and you can still pick an instructor from the list.',
                                linkedCategory: 'functional',
                            },
                            {
                                title: 'More information',
                                description:
                                    'See our <a href="/policy/CookiePolicy.pdf" target="_blank" rel="noopener noreferrer" class="cc__link">Cookie Policy</a> and <a href="/policy/PrivacyPolicy.pdf" target="_blank" rel="noopener noreferrer" class="cc__link">Privacy Policy</a>.',
                            },
                        ],
                    },
                },
            },
        },
    });
}

export function openCookiePreferences(): void {
    CookieConsent.showPreferences();
}

export function hasFunctionalConsent(): boolean {
    return CookieConsent.acceptedCategory('functional');
}
