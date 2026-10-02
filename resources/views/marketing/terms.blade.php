@extends('layouts.marketing')
@section('title', 'Terms of Service — Merqio POS')
@section('meta_description', 'The terms and conditions governing your access to and use of the Merqio POS platform.')

@section('content')

<section class="mkt-page-hero">
    <div class="mkt-container">
        <span class="mkt-eyebrow">Legal</span>
        <h1>Terms of Service</h1>
        <p>The rules governing your use of Merqio POS.</p>
    </div>
</section>

<section class="mkt-section" style="padding-top:40px;">
    <div class="mkt-legal">
        <p class="mkt-legal-updated">Last updated: 1 October 2026 &nbsp;·&nbsp; Effective: 1 October 2026</p>

        <p>These Terms of Service ("Terms") constitute a legally binding agreement between you ("you", "your", or, where you register on behalf of a business, "Merchant") and Merqio POS ("we", "us", "our"), governing your access to and use of the cloud-based business management platform operated at <strong>merqiopos.com</strong> and its associated applications (the "Platform"). By creating an account, accessing, or otherwise using the Platform, you agree to be bound by these Terms, our <a href="{{ route('privacy') }}">Privacy Policy</a>, and our <a href="{{ route('disclaimer') }}">Disclaimer</a>, each of which is incorporated into these Terms by reference. If you do not agree to all of these documents, you must not access or use the Platform.</p>

        <h2>1. Definitions</h2>
        <ul>
            <li><strong>"Organisation Account"</strong> means the top-level account under which one or more of a Merchant's stores or branches are managed.</li>
            <li><strong>"Merchant Data"</strong> means all data entered into the Platform by a Merchant or its authorised staff, including products, sales, customers, suppliers, staff records, and payroll figures.</li>
            <li><strong>"Subscription Plan"</strong> means the tier of paid or free service selected by a Merchant, as described on our <a href="{{ route('pricing') }}">pricing page</a>.</li>
            <li><strong>"Authorised User"</strong> means any individual a Merchant grants access to the Platform under its Organisation Account, including owners, managers, cashiers, and staff.</li>
        </ul>

        <h2>2. Acceptance and Eligibility</h2>
        <p>You must be at least 18 years old and have the legal capacity to enter into a binding contract to register for an account. By registering, you represent and warrant that you meet these requirements and that all information you provide is accurate, current, and complete. If you register on behalf of a business or organisation, you represent that you have the authority to bind that entity to these Terms, and "you" in these Terms then refers to that entity as well as to you personally.</p>

        <h2>3. Description of the Service</h2>
        <p>Merqio POS provides a cloud-based business management platform for small and medium enterprises operating in Kenya, offering tools that may include: point-of-sale and inventory management, sales and expense tracking, customer relationship and credit management, invoicing and quotations, supplier and purchase order management, multi-branch and multi-store management, staff and role management, payroll processing (including PAYE, NSSF, and SHIF computations), M-Pesa and card payment collection, SMS and communication tools, an online storefront, and reporting.</p>
        <p>The specific features available to you depend on your Subscription Plan. We reserve the right to modify, add to, suspend, or discontinue any feature or part of the Platform at any time. Where reasonably practicable, we will give at least 14 days' notice of changes that materially reduce the functionality available to you.</p>

        <h2>4. Accounts and Access</h2>
        <h3>4.1 Registration and security</h3>
        <p>You are responsible for maintaining the confidentiality of your login credentials and for all activity that occurs under your account or any Authorised User's access, whether or not you authorised that activity. You must notify us immediately at <a href="mailto:support@merqiopos.com">support@merqiopos.com</a> if you become aware of any unauthorised access to or use of your account.</p>
        <h3>4.2 One organisation, multiple stores</h3>
        <p>Each store or branch is managed under a single Organisation Account. You may add Authorised Users with role-based permissions appropriate to their function. You are responsible for the actions of every Authorised User you add, and for revoking access promptly when a staff member leaves your business.</p>
        <h3>4.3 Accuracy of business information</h3>
        <p>You are solely responsible for the accuracy of all business information you configure in the Platform, including your KRA PIN, VAT registration status and rate, statutory deduction settings, and any figures used to generate invoices, receipts, or payroll documents. See our <a href="{{ route('disclaimer') }}">Disclaimer</a> for important limitations on statutory calculations performed by the Platform.</p>

        <h2>5. Subscription Plans and Billing</h2>
        <h3>5.1 Plans and pricing</h3>
        <p>We offer three paid plans — Solo, Growth, and Enterprise. There is no permanent free tier. Current features and pricing are set out on our <a href="{{ route('pricing') }}">pricing page</a>, which forms part of these Terms. Prices are quoted in Kenyan Shillings (KSh) and are inclusive of any applicable taxes unless stated otherwise.</p>
        <h3>5.2 Payment and renewal</h3>
        <p>Paid Subscription Plans are billed monthly in advance via M-Pesa or other payment methods we make available at checkout. Your subscription renews automatically at the end of each billing period unless you cancel before that date. You authorise us (or our payment processor) to charge your selected payment method for each renewal.</p>
        <h3>5.3 Free trial</h3>
        <p>New Organisation Accounts begin on the Solo plan with a 1-month free trial and no payment required to begin. Growth and Enterprise do not include a trial. At the end of the trial period, if you have not selected and paid for a plan, access to your account is paused until you choose and pay for a plan — you are never charged automatically without having actively chosen and paid for a paid plan.</p>
        <h3>5.4 Refunds and cancellation</h3>
        <p>You may cancel a paid Subscription Plan at any time from account settings; you will retain access to paid features until the end of the billing period you have already paid for, after which access to your account is paused until you choose a new plan. We do not offer refunds for partial billing periods, save at our sole discretion in exceptional circumstances (for example, a documented and sustained service outage attributable to us).</p>
        <h3>5.5 Changes to pricing</h3>
        <p>We may change Subscription Plan prices with at least 30 days' prior notice by email or in-platform notice. Any price change takes effect from your next renewal date following the notice period.</p>
        <h3>5.6 Taxes</h3>
        <p>You are responsible for any taxes associated with your use of the Platform, other than taxes based on our net income.</p>

        <h2>6. Acceptable Use Policy</h2>
        <p>You agree not to use the Platform to:</p>
        <ul>
            <li>Violate any applicable Kenyan law or regulation, including tax, consumer protection, and anti-money-laundering law</li>
            <li>Store, transmit, or process data that is fraudulent, misleading, or that you do not have the right to collect or use</li>
            <li>Process transactions for illegal goods, services, or activities</li>
            <li>Attempt to gain unauthorised access to any part of the Platform, other accounts, or its underlying infrastructure</li>
            <li>Interfere with, disrupt, or place undue load on the Platform or its servers, including through automated scraping not authorised by us</li>
            <li>Reverse-engineer, decompile, disassemble, or attempt to derive the source code of the Platform, except to the extent such restriction is prohibited by applicable law</li>
            <li>Resell, sublicense, rent, or otherwise make the Platform available to any third party without our prior written consent</li>
            <li>Introduce any virus, malware, or other harmful code into the Platform</li>
            <li>Impersonate any person or entity, or misrepresent your affiliation with any person or entity</li>
        </ul>
        <p>We may investigate suspected violations of this policy and may suspend or terminate an account, without prior notice in serious cases, where a violation is confirmed or reasonably suspected.</p>

        <h2>7. Merchant Data and Ownership</h2>
        <p>You retain full ownership of all Merchant Data. You grant us a limited, non-exclusive, worldwide licence to host, store, process, transmit, and display Merchant Data solely to the extent necessary to provide the Platform's functionality to you, comply with law, and as otherwise permitted under our <a href="{{ route('privacy') }}">Privacy Policy</a>. We claim no ownership rights in Merchant Data and will not use it to train, develop, or improve any product or feature offered to third parties, nor disclose it to third parties except as described in the Privacy Policy.</p>
        <p>You may export Merchant Data at any time from account settings in commonly-used formats (such as CSV or PDF, depending on the record type). On account closure, we will keep an export available to you for 30 days before scheduling permanent deletion, subject to the retention requirements described in our Privacy Policy.</p>
        <p>You represent and warrant that you have all necessary rights and consents to enter any personal data of third parties (customers, employees, suppliers) into the Platform, and that doing so does not infringe the rights of any person or violate any applicable law.</p>

        <h2>8. Third-Party Services and Integrations</h2>
        <p>The Platform integrates with third-party services, including Safaricom's M-Pesa (Daraja API), Pesapal, SMS gateway providers, and Google, to enable payment collection, notifications, sign-in, and optional data export. These are independent services not owned or controlled by us. We are not responsible for the availability, accuracy, security, or performance of any third-party service, including delays, failures, or errors in payment confirmation caused by mobile network operators, payment processors, or telecommunications infrastructure outside our control. Your use of any third-party service accessed through the Platform is also subject to that provider's own terms and privacy policy.</p>
        <p>Where you choose to connect a Google account — to sign in, or to enable the optional Google Sheets Export feature — that connection is governed by Google's own terms and privacy policy in addition to these Terms, and by the Google API disclosures in our <a href="{{ route('privacy') }}">Privacy Policy</a>. You may disconnect a connected Google account at any time from your account settings.</p>

        <h2>9. Intellectual Property</h2>
        <p>The Platform, including its underlying software, source code, design, user interface, trademarks, logos, and all content we create (excluding Merchant Data), is owned by us or our licensors and is protected by Kenyan and international intellectual property laws. These Terms grant you a limited, non-exclusive, non-transferable, revocable licence to access and use the Platform for your internal business purposes in accordance with these Terms. No other rights are granted.</p>
        <p>Any feedback, suggestions, or ideas you submit to us regarding the Platform may be used by us without restriction or obligation to you.</p>

        <h2>10. Confidentiality</h2>
        <p>Each party agrees to protect the confidential information of the other party with the same degree of care it uses to protect its own confidential information of similar nature, and not less than reasonable care, and to use such confidential information solely for the purposes of these Terms. This clause does not apply to information that is or becomes publicly available through no fault of the receiving party, or that is required to be disclosed by law.</p>

        <h2>11. Service Availability and Support</h2>
        <p>We aim for at least 99.9% monthly uptime, excluding scheduled maintenance windows, which we will endeavour to schedule outside peak business hours and announce in advance where reasonably possible. In the event of unplanned downtime exceeding four consecutive hours attributable to us, we will, upon request, credit your account with a pro-rata subscription credit for the affected period. This clause does not apply to downtime caused by third-party services described in Section 8, force majeure events described in Section 16, or scheduled maintenance.</p>
        <p>Support is available by email and WhatsApp during the business hours stated on our <a href="{{ route('contact') }}">contact page</a>.</p>

        <h2>12. Disclaimers</h2>
        <p>The Platform is provided <strong>"as is"</strong> and <strong>"as available"</strong>, without warranties of any kind, whether express, implied, or statutory, including but not limited to implied warranties of merchantability, fitness for a particular purpose, and non-infringement, except to the extent such warranties cannot be excluded under Kenyan law. Important additional disclaimers, including as to statutory tax and payroll calculations and third-party payment processing, are set out in full in our <a href="{{ route('disclaimer') }}">Disclaimer</a>, which forms part of these Terms.</p>

        <h2>13. Limitation of Liability</h2>
        <p>To the maximum extent permitted by Kenyan law:</p>
        <ul>
            <li>We are not liable for loss of profits, revenue, business opportunity, goodwill, or anticipated savings</li>
            <li>We are not liable for loss or corruption of data caused by factors outside our reasonable control, including Merchant error, third-party service failure, or force majeure events</li>
            <li>We are not liable for any indirect, incidental, special, consequential, or punitive damages arising from or related to your use of the Platform, even if advised of the possibility of such damages</li>
            <li>Our total aggregate liability to you for any claim arising out of or relating to these Terms or the Platform, in any calendar month, is limited to the subscription fees you paid us for that month</li>
        </ul>
        <p>Nothing in these Terms excludes or limits liability for death or personal injury caused by our negligence, for fraud or fraudulent misrepresentation, or for any other liability that cannot lawfully be excluded or limited under the laws of Kenya.</p>

        <h2>14. Indemnification</h2>
        <p>You agree to indemnify, defend, and hold harmless Merqio POS, its officers, employees, and agents from and against any claims, liabilities, damages, losses, and expenses, including reasonable legal fees, arising out of or in any way connected with: (a) your use or misuse of the Platform; (b) your violation of these Terms; (c) your violation of any applicable law; or (d) your infringement of any third-party right, including any data protection or intellectual property right.</p>

        <h2>15. Term, Suspension, and Termination</h2>
        <p>These Terms take effect when you first access the Platform and continue until terminated as described here. You may close your Organisation Account at any time from account settings. We may suspend access immediately, or terminate your account with notice, if: you breach these Terms; you fail to pay subscription fees when due; your use poses a security or legal risk to us or other users; or we are required to do so by law. Where reasonably practicable, we will provide notice and an opportunity to remedy a breach before termination, except in cases of serious or repeated violations.</p>
        <p>On termination, your right to access the Platform ceases immediately, though provisions of these Terms that by their nature should survive termination — including Sections 7 (Merchant Data, for the export window described), 9 (Intellectual Property), 10 (Confidentiality), 13 (Limitation of Liability), and 14 (Indemnification) — will continue to apply.</p>

        <h2>16. Force Majeure</h2>
        <p>Neither party will be liable for any failure or delay in performance under these Terms to the extent such failure or delay is caused by circumstances beyond that party's reasonable control, including acts of God, natural disaster, war, civil unrest, government action, labour disputes, internet or telecommunications failures, or failures of third-party infrastructure or payment networks.</p>

        <h2>17. Dispute Resolution and Governing Law</h2>
        <p>If a dispute arises out of or relates to these Terms, the parties agree to first attempt in good faith to resolve it through direct negotiation between authorised representatives within 30 days of written notice of the dispute. If the dispute is not resolved through negotiation, it shall be referred to and finally resolved by arbitration in Nairobi, Kenya, in accordance with the Arbitration Act, 1995 (as amended), before a single arbitrator agreed by the parties or, failing agreement, appointed by the Chartered Institute of Arbitrators (Kenya Branch). Nothing in this clause prevents either party from seeking urgent interim or injunctive relief from a court of competent jurisdiction. These Terms and any dispute arising from them are governed by the laws of Kenya, and, subject to the arbitration agreement above, the courts of Nairobi, Kenya have exclusive jurisdiction.</p>

        <h2>18. Assignment</h2>
        <p>You may not assign or transfer these Terms, or any rights or obligations under them, without our prior written consent. We may assign these Terms in connection with a merger, acquisition, reorganisation, or sale of assets, or by operation of law, without your consent, provided the assignee agrees to be bound by these Terms.</p>

        <h2>19. Severability</h2>
        <p>If any provision of these Terms is found to be invalid or unenforceable by a court or arbitrator of competent jurisdiction, that provision will be limited or eliminated to the minimum extent necessary, and the remaining provisions will continue in full force and effect.</p>

        <h2>20. Waiver</h2>
        <p>No failure or delay by either party in exercising any right under these Terms will operate as a waiver of that right, nor will any single or partial exercise of a right preclude any other or further exercise of it.</p>

        <h2>21. Entire Agreement</h2>
        <p>These Terms, together with our Privacy Policy, Disclaimer, and pricing page, constitute the entire agreement between you and us regarding the Platform, and supersede any prior agreements or understandings, whether written or oral, relating to the same subject matter.</p>

        <h2>22. Notices</h2>
        <p>We may provide notices to you under these Terms by email to the address associated with your account, or by posting a notice within the Platform. You may provide notices to us at the contact details in Section 24.</p>

        <h2>23. Changes to These Terms</h2>
        <p>We may update these Terms from time to time. We will notify you of material changes by email or through the Platform at least 14 days before they take effect. The "Last updated" date at the top of this page reflects the most recent revision. Your continued use of the Platform after that date constitutes your acceptance of the updated Terms.</p>

        <h2>24. Contact</h2>
        <p>If you have questions about these Terms, please contact us:</p>
        <ul>
            <li>Email: <a href="mailto:support@merqiopos.com">support@merqiopos.com</a></li>
            <li>Phone / WhatsApp: <a href="tel:+254718215432">+254 718 215 432</a></li>
            <li>Address: Nairobi, Kenya</li>
        </ul>
    </div>
</section>

@endsection
