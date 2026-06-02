<?php
/**
 * mb Kommunikation + Events – Kontakt
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$page_title  = 'Kontakt';
$active_page = 'kontakt';

$settings = get_settings();

$s_email     = $settings['contact_email']   ?? 'info@mb-kommunikation-events.ch';
$s_phone     = $settings['contact_phone']   ?? '';
$s_address   = $settings['contact_address'] ?? 'Hochdorf, Luzern, Schweiz';
$s_instagram = $settings['instagram_url']   ?? '';
$s_linkedin  = $settings['linkedin_url']    ?? '';

// ----------------------------------------------------------------
// Form handling
// ----------------------------------------------------------------
$form_success = false;
$form_error   = '';
$field_errors = [];
$old_values   = ['name' => '', 'email' => '', 'phone' => '', 'subject' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $form_error = 'Ungültige Anfrage. Bitte laden Sie die Seite neu und versuchen Sie es erneut.';
    } else {
        $name    = sanitize_input($_POST['name']    ?? '');
        $email   = sanitize_input($_POST['email']   ?? '');
        $phone   = sanitize_input($_POST['phone']   ?? '');
        $subject = sanitize_input($_POST['subject'] ?? '');
        $message = sanitize_input($_POST['message'] ?? '');

        $old_values = compact('name', 'email', 'phone', 'subject', 'message');

        if (empty($name) || mb_strlen($name) < 2) {
            $field_errors['name'] = 'Bitte geben Sie Ihren vollständigen Namen ein (mind. 2 Zeichen).';
        } elseif (mb_strlen($name) > 100) {
            $field_errors['name'] = 'Der Name darf maximal 100 Zeichen lang sein.';
        }
        if (empty($email)) {
            $field_errors['email'] = 'Bitte geben Sie Ihre E-Mail-Adresse ein.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $field_errors['email'] = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
        }
        if (!empty($phone) && !preg_match('/^[+\d\s\(\)\-\.]{6,30}$/', $phone)) {
            $field_errors['phone'] = 'Bitte geben Sie eine gültige Telefonnummer ein.';
        }
        if (empty($subject)) {
            $field_errors['subject'] = 'Bitte wählen Sie einen Betreff aus.';
        }
        if (empty($message) || mb_strlen($message) < 10) {
            $field_errors['message'] = 'Bitte geben Sie eine Nachricht ein (mind. 10 Zeichen).';
        } elseif (mb_strlen($message) > 3000) {
            $field_errors['message'] = 'Die Nachricht darf maximal 3000 Zeichen lang sein.';
        }

        // Honeypot anti-spam
        if (!empty($_POST['website_url'])) {
            $form_success = true;
        } elseif (empty($field_errors)) {
            try {
                $db = Database::getInstance();
                $db->insert('contact_messages', [
                    'name'    => $name,
                    'email'   => $email,
                    'phone'   => $phone ?: null,
                    'subject' => $subject,
                    'message' => $message,
                ]);
                // Notify admin
                $admin_body = build_admin_notification_email(compact('name','email','phone','subject','message'));
                send_mail($s_email, 'Neue Kontaktanfrage: ' . $subject, $admin_body, $email);
                // Confirmation to visitor
                $confirm_body = build_confirmation_email($name, $subject);
                send_mail($email, 'Ihre Anfrage bei mb Kommunikation + Events', $confirm_body);
                $form_success = true;
                $old_values   = ['name' => '', 'email' => '', 'phone' => '', 'subject' => '', 'message' => ''];
            } catch (Throwable $e) {
                error_log('[Kontakt] Error: ' . $e->getMessage());
                $form_error = 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut oder schreiben Sie uns direkt per E-Mail.';
            }
        }
    }
}

$subjects = [
    'Kommunikationsberatung', 'Drucksachen', 'Website & Digital',
    'Werbetechnik', 'Eventberatung', 'Eventkonzept', 'Eventdurchführung',
    'Firmenevent', 'Team-Event', 'Jubiläum / Feier', 'Anderes',
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- ================================================================
     HERO
     ================================================================ -->
<section class="relative bg-primary overflow-hidden py-20 lg:py-28">
    <div class="absolute inset-0 opacity-5"
         style="background-image:radial-gradient(#fff 1px,transparent 1px);background-size:28px 28px;"></div>
    <div class="absolute -top-20 -right-20 w-80 h-80 rounded-full bg-accent/10 blur-3xl"></div>
    <div class="absolute -bottom-12 -left-12 w-72 h-72 rounded-full bg-gold/10 blur-3xl"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <nav class="flex items-center justify-center gap-2 text-blue-300 text-sm mb-6">
            <a href="/" class="hover:text-white transition-colors">Home</a>
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
            <span class="text-white font-medium">Kontakt</span>
        </nav>
        <p class="text-accent font-semibold text-sm uppercase tracking-widest mb-4">Nehmen Sie Kontakt auf</p>
        <h1 class="text-4xl sm:text-5xl font-extrabold text-white leading-tight mb-5">
            Wir freuen uns auf<br>Ihre Nachricht
        </h1>
        <p class="text-blue-200 text-xl max-w-2xl mx-auto">
            Erzählen Sie uns von Ihrem Projekt – wir melden uns so schnell wie möglich bei Ihnen.
        </p>
    </div>
    <div class="absolute bottom-0 left-0 right-0 leading-none">
        <svg viewBox="0 0 1440 60" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" class="w-full h-12">
            <path d="M0,60 L0,30 Q360,0 720,30 Q1080,60 1440,30 L1440,60 Z" fill="#f9fafb"/>
        </svg>
    </div>
</section>


<!-- ================================================================
     CONTACT LAYOUT
     ================================================================ -->
<section class="py-20 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-5 gap-12 items-start">

            <!-- FORM (3 cols) -->
            <div class="lg:col-span-3">
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 sm:p-10">

                    <?php if ($form_success): ?>
                    <div class="text-center py-12">
                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-100 mb-6">
                            <i data-lucide="check-circle" class="w-10 h-10 text-green-600"></i>
                        </div>
                        <h2 class="text-2xl font-extrabold text-primary mb-3">Vielen Dank!</h2>
                        <p class="text-gray-600 leading-relaxed mb-8 max-w-sm mx-auto">
                            Ihre Nachricht ist bei uns eingegangen. Wir melden uns so schnell wie möglich bei Ihnen – in der Regel innert 24 Stunden.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            <a href="/" class="inline-flex items-center justify-center gap-2 bg-primary text-white font-bold px-8 py-3 rounded-xl hover:bg-primary-dark transition-colors">
                                <i data-lucide="home" class="w-4 h-4"></i>Zur Startseite
                            </a>
                            <a href="/events.php" class="inline-flex items-center justify-center gap-2 border-2 border-primary/10 text-primary font-bold px-8 py-3 rounded-xl hover:bg-primary hover:text-white hover:border-primary transition-all">
                                <i data-lucide="calendar" class="w-4 h-4"></i>Events ansehen
                            </a>
                        </div>
                    </div>

                    <?php else: ?>

                    <div class="mb-8">
                        <h2 class="text-2xl font-extrabold text-primary mb-2">Nachricht senden</h2>
                        <p class="text-gray-500 text-sm">Alle Felder ausser Telefon sind Pflichtfelder.</p>
                    </div>

                    <?php if ($form_error): ?>
                    <div class="mb-6 flex items-start gap-3 p-4 bg-red-50 border border-red-200 rounded-xl text-red-800">
                        <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0 mt-0.5 text-red-500"></i>
                        <p class="text-sm font-medium"><?= e($form_error) ?></p>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="/kontakt.php" novalidate
                          x-data="{ charCount: <?= mb_strlen($old_values['message']) ?> }">
                        <?= csrf_field() ?>

                        <!-- Honeypot -->
                        <div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
                            <input type="text" name="website_url" tabindex="-1" autocomplete="off">
                        </div>

                        <!-- Name + Email -->
                        <div class="grid sm:grid-cols-2 gap-5 mb-5">
                            <div>
                                <label for="name" class="block text-sm font-semibold text-gray-700 mb-1.5">
                                    Name <span class="text-accent">*</span>
                                </label>
                                <input type="text" id="name" name="name"
                                       value="<?= e($old_values['name']) ?>"
                                       required autocomplete="name"
                                       placeholder="Ihr vollständiger Name"
                                       class="w-full px-4 py-3 rounded-xl border text-sm outline-none transition-colors focus:ring-2 focus:ring-primary/20
                                           <?= isset($field_errors['name']) ? 'border-red-400 bg-red-50 focus:border-red-500' : 'border-gray-200 bg-gray-50 focus:border-primary focus:bg-white' ?>">
                                <?php if (isset($field_errors['name'])): ?>
                                <p class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                                    <i data-lucide="alert-circle" class="w-3 h-3"></i><?= e($field_errors['name']) ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-semibold text-gray-700 mb-1.5">
                                    E-Mail <span class="text-accent">*</span>
                                </label>
                                <input type="email" id="email" name="email"
                                       value="<?= e($old_values['email']) ?>"
                                       required autocomplete="email"
                                       placeholder="ihre@email.ch"
                                       class="w-full px-4 py-3 rounded-xl border text-sm outline-none transition-colors focus:ring-2 focus:ring-primary/20
                                           <?= isset($field_errors['email']) ? 'border-red-400 bg-red-50 focus:border-red-500' : 'border-gray-200 bg-gray-50 focus:border-primary focus:bg-white' ?>">
                                <?php if (isset($field_errors['email'])): ?>
                                <p class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                                    <i data-lucide="alert-circle" class="w-3 h-3"></i><?= e($field_errors['email']) ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Phone + Subject -->
                        <div class="grid sm:grid-cols-2 gap-5 mb-5">
                            <div>
                                <label for="phone" class="block text-sm font-semibold text-gray-700 mb-1.5">
                                    Telefon <span class="text-gray-400 font-normal text-xs">(optional)</span>
                                </label>
                                <input type="tel" id="phone" name="phone"
                                       value="<?= e($old_values['phone']) ?>"
                                       autocomplete="tel"
                                       placeholder="+41 79 000 00 00"
                                       class="w-full px-4 py-3 rounded-xl border text-sm outline-none transition-colors focus:ring-2 focus:ring-primary/20
                                           <?= isset($field_errors['phone']) ? 'border-red-400 bg-red-50 focus:border-red-500' : 'border-gray-200 bg-gray-50 focus:border-primary focus:bg-white' ?>">
                                <?php if (isset($field_errors['phone'])): ?>
                                <p class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                                    <i data-lucide="alert-circle" class="w-3 h-3"></i><?= e($field_errors['phone']) ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label for="subject" class="block text-sm font-semibold text-gray-700 mb-1.5">
                                    Betreff <span class="text-accent">*</span>
                                </label>
                                <select id="subject" name="subject" required
                                        class="w-full px-4 py-3 rounded-xl border text-sm outline-none transition-colors focus:ring-2 focus:ring-primary/20 appearance-none
                                            <?= isset($field_errors['subject']) ? 'border-red-400 bg-red-50 focus:border-red-500' : 'border-gray-200 bg-gray-50 focus:border-primary focus:bg-white' ?>"
                                        style="background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E\");background-position:right 0.75rem center;background-size:1.25rem;background-repeat:no-repeat;">
                                    <option value="" disabled <?= empty($old_values['subject']) ? 'selected' : '' ?>>– Bitte wählen –</option>
                                    <?php foreach ($subjects as $subj): ?>
                                    <option value="<?= e($subj) ?>" <?= $old_values['subject'] === $subj ? 'selected' : '' ?>><?= e($subj) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($field_errors['subject'])): ?>
                                <p class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                                    <i data-lucide="alert-circle" class="w-3 h-3"></i><?= e($field_errors['subject']) ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Message -->
                        <div class="mb-6">
                            <label for="message" class="block text-sm font-semibold text-gray-700 mb-1.5">
                                Nachricht <span class="text-accent">*</span>
                            </label>
                            <textarea id="message" name="message" rows="6"
                                      required maxlength="3000"
                                      placeholder="Erzählen Sie uns von Ihrem Projekt, Event oder Ihrer Anfrage…"
                                      @input="charCount = $event.target.value.length"
                                      class="w-full px-4 py-3 rounded-xl border text-sm outline-none transition-colors focus:ring-2 focus:ring-primary/20 resize-none
                                          <?= isset($field_errors['message']) ? 'border-red-400 bg-red-50 focus:border-red-500' : 'border-gray-200 bg-gray-50 focus:border-primary focus:bg-white' ?>"><?= e($old_values['message']) ?></textarea>
                            <div class="flex items-start justify-between mt-1.5">
                                <div>
                                    <?php if (isset($field_errors['message'])): ?>
                                    <p class="text-xs text-red-600 flex items-center gap-1">
                                        <i data-lucide="alert-circle" class="w-3 h-3"></i><?= e($field_errors['message']) ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-gray-400 ml-auto"><span x-text="charCount"></span> / 3000</p>
                            </div>
                        </div>

                        <!-- Privacy note -->
                        <p class="text-xs text-gray-400 mb-6 leading-relaxed">
                            Mit dem Absenden stimmen Sie der Verarbeitung Ihrer Daten gemäss unserer
                            <a href="/datenschutz.php" class="text-primary hover:underline">Datenschutzerklärung</a> zu.
                        </p>

                        <button type="submit"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-accent text-white font-bold px-10 py-4 rounded-xl shadow-md hover:bg-accent-dark hover:shadow-lg transition-all duration-200">
                            <i data-lucide="send" class="w-5 h-5"></i>
                            Nachricht senden
                        </button>
                    </form>

                    <?php endif; ?>
                </div>
            </div>


            <!-- SIDEBAR (2 cols) -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Company info -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="flex items-center justify-center w-14 h-14 rounded-xl bg-primary text-white font-black text-xl shadow-md flex-shrink-0">mb</div>
                        <div>
                            <p class="text-primary font-extrabold text-lg leading-tight">mb Kommunikation</p>
                            <p class="text-gray-500 text-sm">+ Events · Hochdorf</p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-start gap-3.5">
                            <span class="flex-shrink-0 flex items-center justify-center w-9 h-9 rounded-lg bg-primary/5">
                                <i data-lucide="user" class="w-4 h-4 text-primary"></i>
                            </span>
                            <div>
                                <p class="text-gray-800 font-semibold text-sm">Manuela Bischof</p>
                                <p class="text-gray-400 text-xs">Inhaberin</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3.5">
                            <span class="flex-shrink-0 flex items-center justify-center w-9 h-9 rounded-lg bg-primary/5">
                                <i data-lucide="map-pin" class="w-4 h-4 text-primary"></i>
                            </span>
                            <p class="text-gray-600 text-sm self-center"><?= e($s_address) ?></p>
                        </div>
                        <a href="mailto:<?= e($s_email) ?>" class="flex items-start gap-3.5 group">
                            <span class="flex-shrink-0 flex items-center justify-center w-9 h-9 rounded-lg bg-primary/5 group-hover:bg-accent transition-colors">
                                <i data-lucide="mail" class="w-4 h-4 text-primary group-hover:text-white transition-colors"></i>
                            </span>
                            <p class="text-gray-600 text-sm self-center group-hover:text-accent transition-colors break-all"><?= e($s_email) ?></p>
                        </a>
                        <?php if ($s_phone): ?>
                        <a href="tel:<?= preg_replace('/[^+\d]/', '', $s_phone) ?>" class="flex items-start gap-3.5 group">
                            <span class="flex-shrink-0 flex items-center justify-center w-9 h-9 rounded-lg bg-primary/5 group-hover:bg-accent transition-colors">
                                <i data-lucide="phone" class="w-4 h-4 text-primary group-hover:text-white transition-colors"></i>
                            </span>
                            <p class="text-gray-600 text-sm self-center group-hover:text-accent transition-colors"><?= e($s_phone) ?></p>
                        </a>
                        <?php endif; ?>
                    </div>

                    <?php if ($s_instagram || $s_linkedin): ?>
                    <div class="mt-6 pt-6 border-t border-gray-100 flex flex-wrap gap-2">
                        <?php if ($s_instagram): ?>
                        <a href="<?= e($s_instagram) ?>" target="_blank" rel="noopener noreferrer"
                           class="flex items-center gap-2 text-xs font-semibold text-gray-500 hover:text-accent transition-colors px-3 py-2 rounded-lg hover:bg-accent/5">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                            Instagram
                        </a>
                        <?php endif; ?>
                        <?php if ($s_linkedin): ?>
                        <a href="<?= e($s_linkedin) ?>" target="_blank" rel="noopener noreferrer"
                           class="flex items-center gap-2 text-xs font-semibold text-gray-500 hover:text-[#0077b5] transition-colors px-3 py-2 rounded-lg hover:bg-blue-50">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                            LinkedIn
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Availability card -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-primary font-bold text-sm mb-4 flex items-center gap-2">
                        <i data-lucide="clock" class="w-4 h-4 text-accent"></i>
                        Erreichbarkeit
                    </h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between text-gray-600">
                            <span>Montag – Freitag</span>
                            <span class="font-semibold text-primary">08:00 – 18:00</span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Samstag</span>
                            <span class="font-semibold text-primary">nach Vereinbarung</span>
                        </div>
                    </div>
                </div>

                <!-- Trust card -->
                <div class="bg-primary rounded-3xl p-8 text-white">
                    <h3 class="font-extrabold text-lg mb-5 flex items-center gap-2">
                        <i data-lucide="shield-check" class="w-5 h-5 text-gold"></i>
                        Schnell &amp; unkompliziert
                    </h3>
                    <div class="space-y-4">
                        <?php foreach ([
                            ['icon' => 'zap',          'title' => 'Antwort innerhalb 24h',  'desc' => 'In der Regel melden wir uns am selben oder nächsten Werktag.'],
                            ['icon' => 'shield-check', 'title' => 'Kostenlose Erstberatung', 'desc' => 'Unverbindliches Erstgespräch ist bei uns immer gratis.'],
                            ['icon' => 'heart',        'title' => 'Persönlich & direkt',     'desc' => 'Sie sprechen immer mit Manuela Bischof persönlich.'],
                        ] as $info): ?>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-lg bg-white/10 mt-0.5">
                                <i data-lucide="<?= $info['icon'] ?>" class="w-4 h-4 text-gold"></i>
                            </div>
                            <div>
                                <p class="text-white text-sm font-semibold"><?= e($info['title']) ?></p>
                                <p class="text-blue-200 text-xs leading-relaxed"><?= e($info['desc']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Map placeholder -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="h-48 bg-gradient-to-br from-blue-50 to-gray-200 flex items-center justify-center">
                        <!--
                            Replace with Google Maps embed:
                            <iframe src="https://www.google.com/maps/embed?pb=..." width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                        -->
                        <div class="text-center">
                            <i data-lucide="map" class="w-12 h-12 text-gray-300 mx-auto mb-2"></i>
                            <p class="text-gray-500 text-sm font-semibold">Hochdorf, Luzern</p>
                            <p class="text-gray-400 text-xs">Schweiz</p>
                        </div>
                    </div>
                    <div class="px-5 py-4 flex items-center justify-between">
                        <div>
                            <p class="text-primary font-semibold text-sm">Hochdorf</p>
                            <p class="text-gray-400 text-xs">Kanton Luzern, Schweiz</p>
                        </div>
                        <a href="https://maps.google.com/?q=Hochdorf,+Luzern,+Schweiz" target="_blank" rel="noopener noreferrer"
                           class="flex items-center gap-1.5 text-xs font-semibold text-accent hover:text-accent-dark transition-colors">
                            Auf Maps öffnen <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                        </a>
                    </div>
                </div>

            </div><!-- /sidebar -->
        </div>
    </div>
</section>


<!-- ================================================================
     FAQ
     ================================================================ -->
<section class="py-20 bg-white">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <p class="text-accent font-semibold text-sm uppercase tracking-widest mb-3">Häufige Fragen</p>
            <h2 class="text-3xl font-extrabold text-primary mb-3">FAQ</h2>
            <p class="text-gray-500">Antworten auf die wichtigsten Fragen.</p>
        </div>

        <div class="space-y-4" x-data="{ open: null }">
            <?php
            $faqs = [
                ['q' => 'Wie schnell erhalte ich eine Antwort?',
                 'a' => 'In der Regel innerhalb von 24 Stunden. Bei dringenden Anfragen können Sie uns direkt per E-Mail oder Telefon kontaktieren.'],
                ['q' => 'Arbeiten Sie auch mit kleinen Unternehmen und Privatpersonen?',
                 'a' => 'Ja! Wir betreuen Kunden jeder Grösse – von Einzelpersonen über Vereine bis hin zu KMU in der ganzen Zentralschweiz.'],
                ['q' => 'Ist das Erstgespräch kostenlos?',
                 'a' => 'Ja, das Erstgespräch ist vollständig kostenlos und unverbindlich. Wir nehmen uns Zeit, Ihre Bedürfnisse zu verstehen.'],
                ['q' => 'Wie weit im Voraus sollte ich ein Event buchen?',
                 'a' => 'Je früher, desto besser. Für kleine Events genügen oft 4–6 Wochen, für grössere Projekte empfehlen wir 3–6 Monate Vorlaufzeit.'],
                ['q' => 'Was kostet eine Kommunikationsberatung?',
                 'a' => 'Die Kosten richten sich nach Umfang und Komplexität. Wir erstellen Ihnen nach dem Erstgespräch gerne ein individuelles Angebot.'],
            ];
            foreach ($faqs as $i => $faq): ?>
            <div class="border border-gray-100 rounded-2xl overflow-hidden bg-white shadow-sm"
                 x-data="{ id: <?= $i ?> }">
                <button @click="$parent.open = $parent.open === id ? null : id"
                        class="w-full flex items-center justify-between gap-4 px-6 py-5 text-left hover:bg-gray-50 transition-colors">
                    <span class="text-primary font-semibold text-sm sm:text-base"><?= e($faq['q']) ?></span>
                    <i data-lucide="chevron-down"
                       class="w-5 h-5 text-gray-400 flex-shrink-0 transition-transform duration-200"
                       :class="$parent.open === id ? 'rotate-180 text-accent' : ''"></i>
                </button>
                <div x-show="$parent.open === id"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-cloak class="px-6 pb-5">
                    <p class="text-gray-600 text-sm leading-relaxed border-t border-gray-100 pt-4"><?= e($faq['a']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
