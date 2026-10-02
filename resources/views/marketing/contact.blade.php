@extends('layouts.marketing')
@section('title', 'Contact Us — Merqio POS')
@section('meta_description', 'Get in touch with the Merqio POS team. We are based in Nairobi, Kenya and here to help your business grow.')

@section('content')

<section class="mkt-page-hero">
    <div class="mkt-container">
        <span class="mkt-eyebrow">Contact</span>
        <h1>We'd love to hear from you</h1>
        <p>Question about pricing, features, or your account? We're a team of real people in Nairobi and we reply fast.</p>
    </div>
</section>

<section class="mkt-section" style="padding-bottom:48px;">
    <div class="mkt-container">
        <div class="mkt-contact-grid">

            {{-- Info --}}
            <div>
                <div class="mkt-contact-info-item">
                    <div class="mkt-contact-icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22 6 12 13 2 6"/></svg></div>
                    <div>
                        <strong>Email</strong>
                        <a href="mailto:support@merqiopos.com">support@merqiopos.com</a>
                    </div>
                </div>
                <div class="mkt-contact-info-item">
                    <div class="mkt-contact-icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.68 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.54 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.18 6.18l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg></div>
                    <div>
                        <strong>WhatsApp / Phone</strong>
                        <a href="tel:+254718215432">+254 718 215 432</a>
                    </div>
                </div>
                <div class="mkt-contact-info-item">
                    <div class="mkt-contact-icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
                    <div>
                        <strong>Location</strong>
                        <span>Nairobi, Kenya</span>
                    </div>
                </div>
                <div class="mkt-contact-info-item">
                    <div class="mkt-contact-icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                    <div>
                        <strong>Support hours</strong>
                        <span>Mon–Fri, 8 am – 6 pm EAT</span>
                    </div>
                </div>

                <div class="mkt-contact-aside-card" style="margin-top:1.75rem;">
                    <div class="mkt-contact-aside-label">Already a customer?</div>
                    <p>Log in for faster support — raise a ticket directly from your account settings, or message us on WhatsApp.</p>
                    @if(auth()->check())
                        <a href="{{ route('dashboard') }}" class="mkt-btn-primary" style="font-size:0.85rem; padding:0.55rem 1.1rem;">Go to Dashboard &rarr;</a>
                    @else
                        <a href="{{ route('login') }}"    class="mkt-btn-primary" style="font-size:0.85rem; padding:0.55rem 1.1rem;">Log in to my account</a>
                    @endif
                </div>

                <div class="mkt-contact-aside-card" style="margin-top:1rem;">
                    <div class="mkt-contact-aside-label">Typical response time</div>
                    <div class="mkt-response-times">
                        <div class="mkt-response-time">
                            <strong>&lt; 2h</strong>
                            <span>WhatsApp</span>
                        </div>
                        <div class="mkt-response-time">
                            <strong>&lt; 24h</strong>
                            <span>Email</span>
                        </div>
                        <div class="mkt-response-time">
                            <strong>Instant</strong>
                            <span>Phone (business hours)</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Form --}}
            <div class="mkt-contact-form">
                <h3>Send us a message</h3>
                <p>Fill in the form and we'll get back to you as soon as possible.</p>

                @if(session('success'))
                    <div class="mkt-alert mkt-alert-success">
                        <span>&#10003;</span> {{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="mkt-alert mkt-alert-error">{{ session('error') }}</div>
                @endif

                <form method="POST" action="{{ route('contact.send') }}">
                    @csrf
                    <div class="mkt-form-grid-2">
                        <div class="mkt-form-group">
                            <label for="name">Your Name *</label>
                            <input type="text" id="name" name="name" value="{{ old('name', auth()->user()?->name) }}"
                                   placeholder="Jane Wanjiru" required>
                            @error('name')<span style="font-size:0.75rem;color:#8a3b30;margin-top:3px;display:block;">{{ $message }}</span>@enderror
                        </div>
                        <div class="mkt-form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" value="{{ old('email', auth()->user()?->email) }}"
                                   placeholder="jane@example.com" required>
                            @error('email')<span style="font-size:0.75rem;color:#8a3b30;margin-top:3px;display:block;">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="mkt-form-group">
                        <label for="phone">Phone / WhatsApp</label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone') }}"
                               placeholder="+254 7XX XXX XXX">
                    </div>

                    <div class="mkt-form-group">
                        <label for="subject">Topic *</label>
                        <select id="subject" name="subject" required>
                            <option value="">— What's this about? —</option>
                            <option value="general"   {{ old('subject') == 'general'   ? 'selected' : '' }}>General enquiry</option>
                            <option value="pricing"   {{ old('subject') == 'pricing'   ? 'selected' : '' }}>Pricing & plans</option>
                            <option value="technical" {{ old('subject') == 'technical' ? 'selected' : '' }}>Technical support</option>
                            <option value="demo"      {{ old('subject') == 'demo'      ? 'selected' : '' }}>Request a demo</option>
                            <option value="other"     {{ old('subject') == 'other'     ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('subject')<span style="font-size:0.75rem;color:#8a3b30;margin-top:3px;display:block;">{{ $message }}</span>@enderror
                    </div>

                    <div class="mkt-form-group">
                        <label for="message">Message *</label>
                        <textarea id="message" name="message" placeholder="Tell us how we can help..." required>{{ old('message') }}</textarea>
                        @error('message')<span style="font-size:0.75rem;color:#8a3b30;margin-top:3px;display:block;">{{ $message }}</span>@enderror
                    </div>

                    <button type="submit" class="mkt-form-submit">Send message &rarr;</button>
                </form>
            </div>
        </div>
    </div>
</section>

@endsection
