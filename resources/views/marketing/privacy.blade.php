@extends('layouts.marketing')
@section('title', 'Privacy Policy — Merqio POS')
@section('meta_description', 'How Merqio POS collects, uses, stores, and protects your personal and business data, in line with the Kenya Data Protection Act, 2019.')

@section('content')

<section class="mkt-page-hero">
    <div class="mkt-container">
        <span class="mkt-eyebrow">Legal</span>
        <h1>Privacy Policy</h1>
        <p>How we collect, use, store, and protect your data.</p>
    </div>
</section>

<section class="mkt-section" style="padding-top:40px;">
    <div class="mkt-legal">
        <p class="mkt-legal-updated">Last updated: 1 October 2026 &nbsp;·&nbsp; Effective: 1 October 2026</p>

        <p>This Privacy Policy ("Policy") is issued by Merqio POS ("Merqio POS", "we", "us", "our"), the operator of the cloud-based business management platform available at <strong>merqiopos.com</strong> and its associated applications (together, the "Platform"). It explains what personal data we collect, why we collect it, how we use, store, and protect it, and the rights available to you under the Kenya Data Protection Act, 2019 ("the Act") and its subsidiary regulations.</p>
        <p>This Policy applies to visitors to our marketing website, registered account holders and their staff ("Merchants" or "you"), and, where relevant, the end customers of a Merchant whose data a Merchant enters into the Platform ("Merchant Customers"). By accessing or using the Platform, you acknowledge that you have read and understood this Policy. If you do not agree with it, please do not use the Platform.</p>

        <h2>1. Definitions</h2>
        <ul>
            <li><strong>"Personal Data"</strong> means any information relating to an identified or identifiable natural person, as defined under the Act.</li>
            <li><strong>"Data Controller"</strong> means the entity that determines the purpose and means of processing personal data.</li>
            <li><strong>"Data Processor"</strong> means an entity that processes personal data on behalf of a data controller.</li>
            <li><strong>"Processing"</strong> means any operation performed on personal data, including collection, storage, use, disclosure, or deletion.</li>
            <li><strong>"Data Subject"</strong> means the individual to whom personal data relates.</li>
            <li><strong>"ODPC"</strong> means the Office of the Data Protection Commissioner of Kenya, the supervisory authority for data protection matters in Kenya.</li>
        </ul>

        <h2>2. Our Role: Controller and Processor</h2>
        <p>In relation to the account, billing, and usage information of Merchants and their staff, Merqio POS acts as a <strong>data controller</strong>. In relation to the data a Merchant enters about their own customers, suppliers, or employees (for example, customer contact details entered for invoicing, or employee records entered for payroll), Merqio POS acts as a <strong>data processor</strong>, and the Merchant acts as the data controller responsible for that data. Merchants are responsible for ensuring they have a lawful basis to collect and process any Merchant Customer or employee data they input into the Platform, and for honouring data subject requests from their own customers and employees.</p>

        <h2>3. Information We Collect</h2>

        <h3>3.1 Account information</h3>
        <p>When you register, we collect your name, email address, phone number, password (stored as a salted cryptographic hash, never in plain text), and business details (business name, industry, physical address, and KRA PIN where provided for tax-invoice purposes).</p>

        <h3>3.2 Business data you or your staff enter</h3>
        <p>All operational data entered into the Platform — products and inventory records, sales transactions, expenses, customer records, supplier records, staff records, payroll figures, and statutory deduction inputs — is entered and controlled by you. We process it solely to provide the Platform's functionality to you and do not use it for any independent purpose of our own, except as described in Section 5.</p>

        <h3>3.3 Payment and financial information</h3>
        <p>Subscription payments are processed through M-Pesa (via Safaricom's Daraja API) and, where enabled, Pesapal (for card and Airtel Money payments). We do not collect, store, or have access to your M-Pesa PIN, full card number, card CVV, or card expiry date — these are entered directly into the relevant payment provider's own secure interface and never pass through our servers. We retain only transaction references, amounts, timestamps, and status codes returned by the payment provider, for billing, reconciliation, and support purposes.</p>

        <h3>3.4 Usage and technical data</h3>
        <p>We automatically collect technical and usage information, including: IP address, browser type and version, device type and operating system, pages and features accessed, timestamps of activity, referring URLs, and error/crash diagnostics. We also keep an activity record of what is done in the Platform: which page or action, which account and store, when, whether it succeeded, and, if it failed, the reason. We do not record what you type into forms. Failed sign-ins keep your IP address and a masked email address. These records are kept for about 60 days, are visible only to the business owner (for their own stores) and to our support engineers, and are used to secure the Platform, diagnose faults, and understand how features are used in aggregate.</p>

        <h3>3.5 Location data</h3>
        <p>Where you provide a business address, delivery address, or warehouse location, we store that address as entered. We do not collect precise device geolocation unless a specific feature (such as delivery tracking, if enabled) requests it, and only with your consent at the time.</p>

        <h3>3.6 Communications</h3>
        <p>If you contact us by email, WhatsApp, phone, or the contact form, we retain the content of that communication and any attachments, to respond to you and to maintain a support history for your account.</p>

        <h2>4. Legal Basis for Processing</h2>
        <p>We process personal data on one or more of the following legal bases recognised under the Act:</p>
        <ul>
            <li><strong>Performance of a contract</strong> — processing necessary to provide the Platform and fulfil our obligations to you as a subscriber.</li>
            <li><strong>Consent</strong> — where you have given clear consent, for example to receive marketing communications or to enable an optional feature that uses device location.</li>
            <li><strong>Legal obligation</strong> — where processing is required to comply with Kenyan tax, statutory, or regulatory law (for example, retaining financial records for the period required by the Kenya Revenue Authority).</li>
            <li><strong>Legitimate interest</strong> — for security, fraud prevention, service improvement, and direct communications about your account, balanced against your rights and freedoms.</li>
        </ul>

        <h2>5. How We Use Your Information</h2>
        <ul>
            <li>To provide, operate, secure, and maintain the Platform</li>
            <li>To create and manage your account and authenticate your access</li>
            <li>To process your subscription, issue receipts, and manage billing</li>
            <li>To send transactional and service notices (security alerts, planned downtime, policy changes)</li>
            <li>To respond to support requests and provide customer service</li>
            <li>To detect, investigate, and prevent fraud, abuse, or security incidents</li>
            <li>To analyse aggregated, de-identified usage patterns to improve and develop the Platform</li>
            <li>To comply with applicable Kenyan law, including tax and financial record-keeping obligations</li>
            <li>With your separate consent, to send you product updates and marketing communications, which you may opt out of at any time</li>
        </ul>
        <p>We do not sell, rent, or trade your personal data or your business data to third parties for their own marketing purposes.</p>

        <h2>6. Third-Party Service Providers</h2>
        <p>We engage a limited number of third-party service providers to operate the Platform, each of whom is contractually bound to protect any personal data they process on our behalf and to use it only for the purpose we specify:</p>
        <ul>
            <li><strong>Safaricom PLC (M-Pesa / Daraja API)</strong> — to process mobile money payments initiated on the Platform.</li>
            <li><strong>Pesapal</strong> — to process card and Airtel Money payments, where enabled by a Merchant.</li>
            <li><strong>SMS gateway providers (Africa's Talking and/or Twilio)</strong> — to deliver SMS notifications you or your customers have opted into.</li>
            <li><strong>Cloud infrastructure and hosting providers</strong> — to store data and run the Platform's servers.</li>
            <li><strong>Email delivery providers</strong> — to send transactional emails (receipts, password resets, notifications).</li>
            <li><strong>Google LLC</strong> — see Section 6.1 below for the specific Google features we offer and what each one accesses.</li>
        </ul>
        <p>We do not control the privacy practices of these third parties beyond our contractual arrangements with them, and their own privacy policies govern any data they process directly (for example, the mobile money PIN entry that occurs entirely within Safaricom's own application or USSD session).</p>

        <h3>6.1 Google API Services</h3>
        <p>The Platform offers two separate, optional features built on Google's APIs. Each requires your explicit consent through Google's own sign-in/consent screen before anything is accessed, and each can be disconnected by you at any time.</p>
        <ul>
            <li><strong>Sign in with Google</strong> — if you choose to sign up or log in using your Google account instead of a password, we request only your name, email address, and Google account ID, to create or match your Merqio POS account. We do not access any other Google data for this feature.</li>
            <li><strong>Google Sheets Export</strong> — an optional feature (Settings → Google Sheets Export) that, only if you explicitly connect your Google account for this purpose, creates a spreadsheet in your own Google Drive and keeps it updated with your business's sales, inventory, customer, and expense records from the Platform, so you can view or analyse them in Google Sheets. This requires Google's Sheets scope, used solely to create and write to that one spreadsheet — we do not read, browse, or access any other file in your Google Drive. You can disconnect this at any time from the same settings page; your spreadsheet remains in your Drive and simply stops receiving updates.</li>
        </ul>
        <p>Merqio POS's use and transfer of information received from Google APIs adheres to the <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener">Google API Services User Data Policy</a>, including the Limited Use requirements. Data obtained through Google APIs is used only to provide or improve the specific feature you enabled, is never used for advertising, is never sold, and is never shared with any third party except as strictly necessary to provide that feature (for example, our cloud infrastructure provider storing the access credentials needed to keep your spreadsheet updated, encrypted at rest). No human at Merqio POS reads the content of your Google Sheets data as part of normal operation; access is limited to automated processes unless you explicitly request support with a connected spreadsheet.</p>

        <h2>7. International and Cross-Border Data Transfers</h2>
        <p>Our primary infrastructure is hosted within Kenya and/or the East African region. Where a service provider described in Section 6 processes data outside Kenya, we take reasonable steps to ensure that any such transfer complies with the cross-border data transfer requirements of the Act, including verifying that the receiving jurisdiction maintains an adequate level of data protection or that appropriate contractual safeguards are in place.</p>

        <h2>8. Data Storage and Security</h2>
        <p>Data is encrypted in transit using TLS/HTTPS and at rest using industry-standard encryption. Access to production systems and data is restricted to authorised personnel on a need-to-know basis, protected by multi-factor authentication where applicable, and logged for audit purposes. We perform automated daily backups.</p>
        <p>In the event of a personal data breach that is likely to result in a risk to your rights and freedoms, we will notify the ODPC without undue delay, and in any event within 72 hours of becoming aware of it, and will notify affected data subjects without undue delay where the breach is likely to result in a high risk to them, in line with the Act.</p>
        <p>No system can be guaranteed to be perfectly secure. You are responsible for keeping your login credentials confidential, using a strong password, enabling two-factor authentication where offered, and promptly notifying us of any suspected unauthorised access to your account.</p>

        <h2>9. Data Sharing and Disclosure</h2>
        <p>Beyond the service providers listed in Section 6, we share personal data only in the following limited circumstances:</p>
        <ul>
            <li><strong>Legal requirements</strong> — where disclosure is required by a court order, a lawful request from a government authority, or applicable Kenyan law, including the Kenya Revenue Authority where required for tax administration.</li>
            <li><strong>Protection of rights</strong> — where necessary to investigate, prevent, or act on suspected illegal activity, fraud, or threats to the safety of any person, or to enforce these terms.</li>
            <li><strong>Business transfers</strong> — if Merqio POS is involved in a merger, acquisition, financing, or sale of assets, personal data may be transferred as part of that transaction, subject to the acquiring party's agreement to honour the commitments in this Policy. We will notify you in advance of any such change of control affecting your data.</li>
            <li><strong>With your consent</strong> — for any other purpose that we disclose to you and to which you consent at the time.</li>
        </ul>

        <h2>10. Cookies and Similar Technologies</h2>
        <p>We use cookies and similar technologies (such as local storage) to keep you signed in, remember your preferences, secure your session, and understand aggregate usage of the Platform. We use only strictly necessary and functional cookies for the operation of the authenticated application; the marketing website may additionally use analytics cookies to help us understand visitor behaviour. You can disable cookies in your browser settings, but doing so may prevent core features of the Platform — including staying signed in — from working correctly.</p>

        <h2>11. Data Retention</h2>
        <p>We retain personal data for as long as your account remains active and for as long as necessary to fulfil the purposes described in this Policy. On closure of your account:</p>
        <ul>
            <li>We will make your business data available for export for 30 days, after which it will be scheduled for deletion.</li>
            <li>Personal account data will be deleted or irreversibly anonymised within 90 days of account closure, except where we are legally required to retain specific records for longer (for example, transaction and billing records, which Kenyan tax law may require us to retain for up to seven years).</li>
            <li>Aggregated, de-identified data that can no longer be linked to you may be retained indefinitely for analytical and product-improvement purposes.</li>
        </ul>

        <h2>12. Your Rights Under the Data Protection Act, 2019</h2>
        <p>As a data subject, you have the right to:</p>
        <ul>
            <li>Be informed of the use to which your personal data is to be put</li>
            <li>Access your personal data in our custody</li>
            <li>Request correction of inaccurate, out-of-date, incomplete, or misleading personal data</li>
            <li>Request deletion or destruction of your personal data that is inaccurate, irrelevant, excessive, or unlawfully processed, or that we are no longer authorised to retain</li>
            <li>Object to the processing of all or part of your personal data</li>
            <li>Request the transfer of your personal data in a structured, commonly-used, machine-readable format (data portability) — you may also export your business data directly from account settings at any time</li>
            <li>Withdraw consent at any time, where processing is based on consent, without affecting the lawfulness of processing carried out before withdrawal</li>
            <li>Lodge a complaint with the Office of the Data Protection Commissioner if you believe your rights under the Act have been infringed</li>
        </ul>
        <p>To exercise any of these rights, contact us using the details in Section 16. We will verify your identity and respond within 14 days as required by the Act, or explain any reasonable extension needed for complex requests.</p>

        <h2>13. Merchant Responsibilities Regarding Their Own Customers and Staff</h2>
        <p>If you use the Platform to record data about your own customers, suppliers, or employees, you are independently responsible, as the data controller for that data, for ensuring you have a lawful basis for collecting and processing it, for providing any required privacy notices to those individuals, and for responding to any data subject requests they make to you directly. Merqio POS processes that data solely on your instructions, as your data processor, to provide the Platform's functionality.</p>

        <h2>14. Automated Decision-Making</h2>
        <p>We do not use your personal data to make decisions that produce legal or similarly significant effects on you through fully automated means without human involvement. Statutory calculators within the Platform (such as PAYE, NSSF, or SHIF computations) are computational tools that apply rates you or we have configured — see our <a href="{{ route('disclaimer') }}">Disclaimer</a> regarding their accuracy and limitations.</p>

        <h2>15. Children's Privacy</h2>
        <p>The Platform is intended for business use by adults aged 18 and over and is not directed at children. We do not knowingly collect personal data from children. If you believe a child has provided us with personal data, please contact us and we will investigate and delete it promptly.</p>

        <h2>16. Changes to This Policy</h2>
        <p>We may update this Policy from time to time to reflect changes in our practices or legal requirements. Where we make material changes, we will notify you by email or by displaying a prominent notice within the Platform at least 14 days before the changes take effect. The "Last updated" date at the top of this page indicates when it was last revised. Your continued use of the Platform after that date constitutes acceptance of the updated Policy.</p>

        <h2>17. Complaints and Contact</h2>
        <p>If you have questions, concerns, or a complaint about this Privacy Policy or how we handle your data, please contact us first so we can try to resolve it directly:</p>
        <ul>
            <li>Email: <a href="mailto:support@merqiopos.com">support@merqiopos.com</a></li>
            <li>Phone / WhatsApp: <a href="tel:+254718215432">+254 718 215 432</a></li>
            <li>Address: Nairobi, Kenya</li>
        </ul>
        <p>If you are not satisfied with our response, you have the right to lodge a complaint directly with the Office of the Data Protection Commissioner of Kenya (ODPC), the independent supervisory authority for data protection matters in Kenya.</p>

        <h2>18. Governing Law</h2>
        <p>This Policy is governed by the laws of Kenya, including the Data Protection Act, 2019 and its subsidiary regulations.</p>
    </div>
</section>

@endsection
