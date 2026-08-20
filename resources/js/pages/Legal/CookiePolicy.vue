<template>
  <LegalLayout
    title="Cookie Policy"
    effective-date="21 May 2026"
    last-updated="21 May 2026"
  >
    <h2>1. About this policy</h2>
    <p>
      This Cookie Policy explains how Watch Learn Drive.com Ltd, trading as SmartDriving ("we", "us", "our"),
      uses cookies and similar technologies on the SmartDriving driving instructor management platform
      (the "Platform"). It should be read together with our <a href="/privacy-policy">Privacy Policy</a>.
    </p>

    <h2>2. What are cookies?</h2>
    <p>
      Cookies are small text files that are placed on your device when you visit a website or use an app. They
      allow the site to remember you, your preferences and your activity. Some cookies last only for the
      duration of your visit ("session cookies"); others remain on your device until they expire or you delete
      them ("persistent cookies").
    </p>
    <p>
      We also use similar technologies such as local storage, session storage and pixel tags. In this policy we
      refer to all of these as "cookies".
    </p>

    <h2>3. The cookies we use</h2>
    <p>
      We use cookies for the limited purposes set out below. <strong>We do not use cookies for advertising or
      behavioural tracking on the Platform.</strong>
    </p>

    <h3>3.1 Strictly necessary cookies</h3>
    <p>
      These cookies are essential for the Platform to function. Without them you cannot log in, make a
      booking or take a payment. They do not require your consent.
    </p>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Cookie</th>
            <th>Purpose</th>
            <th>Duration</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Session cookie</td>
            <td>Maintains your logged-in session</td>
            <td>Session</td>
          </tr>
          <tr>
            <td>CSRF token</td>
            <td>Protects forms from cross-site request forgery</td>
            <td>Session</td>
          </tr>
          <tr>
            <td>Authentication "remember me" token</td>
            <td>Keeps you signed in across visits if you choose this option</td>
            <td>Up to 30 days</td>
          </tr>
          <tr>
            <td>Two-factor authentication cookie</td>
            <td>Remembers verified devices for two-factor authentication where you enable that option</td>
            <td>Up to 30 days</td>
          </tr>
          <tr>
            <td>Cookie preferences</td>
            <td>Remembers your cookie choices</td>
            <td>12 months</td>
          </tr>
        </tbody>
      </table>
    </div>

    <h3>3.2 Functional cookies</h3>
    <p>
      These cookies are not strictly necessary but help the Platform work better. We ask for your consent
      before setting them.
    </p>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Cookie</th>
            <th>Purpose</th>
            <th>Duration</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>UI preferences</td>
            <td>Remembers settings such as dark/light mode and chosen view options</td>
            <td>12 months</td>
          </tr>
          <tr>
            <td>Recent items</td>
            <td>Remembers recently viewed pupils, lessons or pages</td>
            <td>12 months</td>
          </tr>
        </tbody>
      </table>
    </div>

    <h3>3.3 Third-party cookies</h3>
    <p>
      The Platform integrates with the following third-party services, which may set their own cookies when
      their features are used:
    </p>
    <ul>
      <li>
        <strong>Stripe</strong> — for payment processing and fraud prevention. Stripe is a separate controller for
        these cookies. See
        <a href="https://stripe.com/cookie-settings" target="_blank" rel="noopener noreferrer">stripe.com/cookie-settings</a>.
      </li>
      <li>
        <strong>Google Maps</strong> — when a map is displayed within the Platform. See
        <a href="https://policies.google.com/technologies/cookies" target="_blank" rel="noopener noreferrer">policies.google.com/technologies/cookies</a>.
      </li>
    </ul>
    <p>
      We do not have access to or control over these third-party cookies. Please see the relevant third party's
      own cookie policy for full details.
    </p>
    <p>
      We do not use Google Analytics, Facebook Pixel, advertising networks, or behavioural-tracking tools on
      the Platform.
    </p>

    <h2>4. Managing cookies</h2>
    <p>You can manage your cookie preferences in two ways:</p>
    <ul>
      <li><strong>In the Platform:</strong> through the "Cookie preferences" link in the footer at any time.</li>
      <li><strong>In your browser:</strong> through the browser's privacy or cookie settings. You can block or delete cookies, but if you block strictly necessary cookies the Platform will not work correctly.</li>
    </ul>
    <p>Browser-specific guidance:</p>
    <ul>
      <li><strong>Chrome:</strong> <a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener noreferrer">support.google.com/chrome/answer/95647</a></li>
      <li><strong>Safari:</strong> <a href="https://support.apple.com/guide/safari/manage-cookies-sfri11471/mac" target="_blank" rel="noopener noreferrer">support.apple.com/guide/safari/manage-cookies-sfri11471/mac</a></li>
      <li><strong>Firefox:</strong> <a href="https://support.mozilla.org/en-US/kb/cookies-information-websites-store-on-your-computer" target="_blank" rel="noopener noreferrer">support.mozilla.org/en-US/kb/cookies-information-websites-store-on-your-computer</a></li>
      <li><strong>Edge:</strong> <a href="https://support.microsoft.com/en-us/microsoft-edge" target="_blank" rel="noopener noreferrer">support.microsoft.com/en-us/microsoft-edge</a></li>
    </ul>

    <h2>5. Changes to this policy</h2>
    <p>
      We may update this Cookie Policy from time to time. The current version is always available on our
      website with the effective date shown above.
    </p>

    <h2>6. Contact</h2>
    <p>If you have any questions about our use of cookies, please contact:</p>
    <p>
      <strong>Watch Learn Drive.com Ltd t/a SmartDriving</strong><br />
      Email: <a href="mailto:customer.care@smartdriving.co.uk">customer.care@smartdriving.co.uk</a><br />
      Telephone: 0800 09 88 321
    </p>
  </LegalLayout>
</template>

<script setup lang="ts">
import LegalLayout from '@/components/Legal/LegalLayout.vue'
</script>
