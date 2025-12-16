<?php
/**
 * Chatbot template
 *
 * @package Ocade_Fusion_AnythingLLM_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Variables disponibles :
// $container_class, $style, $has_consent, $bot_name, $welcome_message, $placeholder
// $bot_avatar_url, $user_avatar_url, $primary_color, $inline
?>
<div id="ofac-chatbot" 
     class="<?php echo esc_attr( $container_class ); ?>" 
     style="<?php echo esc_attr( $style ); ?>"
     data-consent="<?php echo $has_consent ? 'true' : 'false'; ?>"
     data-inline="<?php echo $inline ? 'true' : 'false'; ?>">

    <!-- Toggle Button (only for floating mode) -->
    <?php if ( ! $inline ) : ?>
    <button type="button" 
            id="ofac-trigger"
            class="ofac-trigger ofac-trigger--<?php echo esc_attr( $position ); ?>"
            aria-label="<?php echo esc_attr( $accessibility->get_label( 'open_chat' ) ); ?>"
            aria-expanded="false"
            aria-controls="ofac-modal">
        <span class="ofac-trigger__icon ofac-trigger__icon--chat" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor">
                <path d="M12 3c5.5 0 10 3.58 10 8s-4.5 8-10 8c-1.24 0-2.43-.18-3.53-.5C5.55 21 2 21 2 21c2.33-2.33 2.7-3.9 2.75-4.5C3.05 15.07 2 13.13 2 11c0-4.42 4.5-8 10-8z"/>
            </svg>
        </span>
        <span class="ofac-trigger__icon ofac-trigger__icon--close" aria-hidden="true" style="display:none;">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor">
                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
            </svg>
        </span>
        <span class="ofac-trigger__badge" aria-hidden="true" style="display:none;">0</span>
    </button>
    <?php endif; ?>

    <!-- Chat Window -->
    <div id="ofac-modal" 
         class="ofac-chat-window ofac-modal <?php echo $inline ? 'ofac-visible ofac-modal--open' : ''; ?> ofac-modal--<?php echo esc_attr( $position ); ?>"
         role="dialog"
         aria-modal="true"
         aria-labelledby="ofac-chat-title"
         aria-describedby="ofac-chat-description"
         <?php echo ! $inline ? 'aria-hidden="true"' : ''; ?>>

        <!-- Header -->
        <header class="ofac-chat-header">
            <div class="ofac-header-info">
                <?php if ( $bot_avatar_url ) : ?>
                <img src="<?php echo esc_url( $bot_avatar_url ); ?>" 
                     alt="" 
                     class="ofac-header-avatar"
                     aria-hidden="true"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="ofac-header-avatar ofac-avatar-default" aria-hidden="true" style="display:none;">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                    </svg>
                </div>
                <?php else : ?>
                <div class="ofac-header-avatar ofac-avatar-default" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                    </svg>
                </div>
                <?php endif; ?>
                <div class="ofac-header-text">
                    <h2 id="ofac-chat-title" class="ofac-header-title">
                        <?php echo esc_html( $bot_name ); ?>
                    </h2>
                    <span id="ofac-status" class="ofac-header-status" aria-live="polite">
                        <?php esc_html_e( 'En ligne', 'anythingllm-chatbot' ); ?>
                    </span>
                </div>
            </div>
            <div class="ofac-header-actions">
                <button type="button" 
                        id="ofac-export"
                        class="ofac-header-btn ofac-btn-export"
                        aria-label="<?php echo esc_attr( $accessibility->get_label( 'export_chat' ) ); ?>"
                        title="<?php esc_attr_e( 'Exporter', 'anythingllm-chatbot' ); ?>">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                        <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                    </svg>
                </button>
                <button type="button" 
                        id="ofac-reset"
                        class="ofac-header-btn ofac-btn-reset"
                        aria-label="<?php echo esc_attr( $accessibility->get_label( 'reset_chat' ) ); ?>"
                        title="<?php esc_attr_e( 'Réinitialiser', 'anythingllm-chatbot' ); ?>">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                        <path d="M17.65 6.35A7.958 7.958 0 0012 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0112 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
                    </svg>
                </button>
                <?php if ( ! $inline ) : ?>
                <button type="button" 
                        id="ofac-close"
                        class="ofac-header-btn ofac-btn-close"
                        aria-label="<?php echo esc_attr( $accessibility->get_label( 'close_chat' ) ); ?>">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </button>
                <?php endif; ?>
            </div>
        </header>

        <!-- Hidden description for screen readers -->
        <p id="ofac-chat-description" class="ofac-sr-only">
            <?php esc_html_e( 'Fenêtre de conversation avec un assistant IA. Utilisez Tab pour naviguer, Entrée pour envoyer un message.', 'anythingllm-chatbot' ); ?>
        </p>

        <!-- Consent Screen (toujours rendu, caché par défaut si consentement déjà donné) -->
        <div id="ofac-consent-screen"
             class="ofac-consent-screen <?php echo $has_consent ? '' : 'ofac-consent--visible'; ?>"
             aria-hidden="<?php echo $has_consent ? 'true' : 'false'; ?>">
            <div class="ofac-consent-content">
                <h3 class="ofac-consent-title">
                    <?php esc_html_e( 'Consentement requis', 'anythingllm-chatbot' ); ?>
                </h3>
                <div class="ofac-consent-text">
                    <?php echo wp_kses_post( $consent->get_consent_text() ); ?>
                </div>
                <?php
                $privacy_url = $consent->get_privacy_policy_url();
                if ( $privacy_url ) :
                ?>
                <p class="ofac-consent-privacy">
                    <a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener">
                        <?php esc_html_e( 'Politique de confidentialité', 'anythingllm-chatbot' ); ?>
                        <span class="ofac-sr-only"><?php esc_html_e( '(s\'ouvre dans un nouvel onglet)', 'anythingllm-chatbot' ); ?></span>
                    </a>
                </p>
                <?php endif; ?>
                <div class="ofac-consent-actions">
                    <button type="button"
                            id="ofac-consent-accept"
                            class="ofac-consent-btn">
                        <?php esc_html_e( 'Accepter', 'anythingllm-chatbot' ); ?>
                    </button>
                    <button type="button"
                            id="ofac-consent-decline"
                            class="ofac-consent-btn">
                        <?php esc_html_e( 'Refuser', 'anythingllm-chatbot' ); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Messages Container -->
        <div id="ofac-messages" 
             class="ofac-messages" 
             role="log" 
             aria-live="polite"
             aria-label="<?php echo esc_attr( $accessibility->get_label( 'messages' ) ); ?>"
             tabindex="0">
            
            <!-- Welcome Message -->
            <div class="ofac-message ofac-message--assistant ofac-welcome-message" data-message-id="welcome">
                <?php if ( $bot_avatar_url ) : ?>
                <img src="<?php echo esc_url( $bot_avatar_url ); ?>" 
                     alt="" 
                     class="ofac-message-avatar"
                     aria-hidden="true"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="ofac-message-avatar ofac-avatar-default" aria-hidden="true" style="display:none;">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                    </svg>
                </div>
                <?php else : ?>
                <div class="ofac-message-avatar ofac-avatar-default" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                    </svg>
                </div>
                <?php endif; ?>
                <div class="ofac-message-content">
                    <div class="ofac-message-bubble">
                        <?php echo wp_kses_post( $welcome_message ); ?>
                    </div>
                </div>
            </div>

            <!-- Messages will be appended here -->
        </div>

        <!-- Typing Indicator -->
        <div id="ofac-typing" class="ofac-typing" aria-hidden="true">
            <div class="ofac-message-avatar ofac-avatar-default" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                </svg>
            </div>
            <div class="ofac-typing__bubble">
                <div class="ofac-typing__dots">
                    <span class="ofac-typing__dot"></span>
                    <span class="ofac-typing__dot"></span>
                    <span class="ofac-typing__dot"></span>
                </div>
            </div>
            <span class="ofac-sr-only"><?php echo esc_html( $accessibility->get_label( 'typing' ) ); ?></span>
        </div>

        <!-- Quick Replies -->
        <div id="ofac-quick-replies" class="ofac-quick-replies" aria-label="<?php esc_attr_e( 'Suggestions', 'anythingllm-chatbot' ); ?>">
            <!-- Quick reply buttons will be inserted here -->
        </div>

        <!-- Input Form -->
        <form id="ofac-input-form" class="ofac-input-form" aria-label="<?php echo esc_attr( $accessibility->get_label( 'compose' ) ); ?>">
            <!-- Honeypot field -->
            <input type="text" 
                   name="ofac_hp_field" 
                   id="ofac_hp_field" 
                   class="ofac-hp-field" 
                   tabindex="-1" 
                   autocomplete="off"
                   aria-hidden="true">

            <div class="ofac-input-wrapper">
                <?php 
                $settings_instance = OFAC_Settings::get_instance();
                if ( $settings_instance->get( 'ofac_enable_file_upload', false ) ) : 
                ?>
                <button type="button" 
                        id="ofac-file-btn"
                        class="ofac-input-btn ofac-upload-btn"
                        aria-label="<?php echo esc_attr( $accessibility->get_label( 'upload' ) ); ?>">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
                        <path d="M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5c0-1.38 1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5v10.5c0 .55-.45 1-1 1s-1-.45-1-1V6H10v9.5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V5c0-2.21-1.79-4-4-4S7 2.79 7 5v12.5c0 3.04 2.46 5.5 5.5 5.5s5.5-2.46 5.5-5.5V6h-1.5z"/>
                    </svg>
                </button>
                <input type="file" 
                       id="ofac-file-input" 
                       class="ofac-file-input"
                       accept="<?php echo esc_attr( $settings_instance->get( 'ofac_allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,txt' ) ); ?>"
                       aria-hidden="true">
                <?php endif; ?>

                <textarea id="ofac-input"
                          name="message"
                          class="ofac-message-input"
                          placeholder="<?php echo esc_attr( $placeholder ); ?>"
                          aria-label="<?php echo esc_attr( $accessibility->get_label( 'message_input' ) ); ?>"
                          maxlength="<?php echo esc_attr( $settings_instance->get( 'ofac_max_message_length', 2000 ) ); ?>"
                          rows="1"
                          required></textarea>

                <button type="submit" 
                        id="ofac-send"
                        class="ofac-input-btn ofac-send-btn"
                        aria-label="<?php echo esc_attr( $accessibility->get_label( 'send_message' ) ); ?>">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
                        <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                    </svg>
                </button>
            </div>

            <!-- Character counter -->
            <div id="ofac-char-counter" class="ofac-char-counter" aria-live="polite" aria-atomic="true">
                <span id="ofac-char-current">0</span>/<span id="ofac-char-max"><?php echo esc_html( $settings_instance->get( 'ofac_max_message_length', 2000 ) ); ?></span>
            </div>
        </form>
    </div>
</div>

<!-- Message Template -->
<template id="ofac-message-template">
    <div class="ofac-message" data-message-id="">
        <div class="ofac-message-avatar"></div>
        <div class="ofac-message-content">
            <div class="ofac-message-bubble"></div>
            <div class="ofac-message-actions">
                <button type="button" class="ofac-action-btn ofac-copy-btn" aria-label="<?php echo esc_attr( $accessibility->get_label( 'copy' ) ); ?>">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
                        <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
                    </svg>
                </button>
                <button type="button" class="ofac-action-btn ofac-feedback-btn ofac-feedback-up" aria-label="<?php esc_attr_e( 'Utile', 'anythingllm-chatbot' ); ?>" data-rating="1">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
                        <path d="M1 21h4V9H1v12zm22-11c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-2z"/>
                    </svg>
                </button>
                <button type="button" class="ofac-action-btn ofac-feedback-btn ofac-feedback-down" aria-label="<?php esc_attr_e( 'Pas utile', 'anythingllm-chatbot' ); ?>" data-rating="-1">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
                        <path d="M15 3H6c-.83 0-1.54.5-1.84 1.22l-3.02 7.05c-.09.23-.14.47-.14.73v2c0 1.1.9 2 2 2h6.31l-.95 4.57-.03.32c0 .41.17.79.44 1.06L9.83 23l6.59-6.59c.36-.36.58-.86.58-1.41V5c0-1.1-.9-2-2-2zm4 0v12h4V3h-4z"/>
                    </svg>
                </button>
            </div>
            <time class="ofac-message-time"></time>
        </div>
    </div>
</template>

<!-- Quick Reply Template -->
<template id="ofac-quick-reply-template">
    <button type="button" class="ofac-quick-reply-btn"></button>
</template>

<!-- Source Template -->
<template id="ofac-source-template">
    <div class="ofac-sources">
        <button type="button" class="ofac-sources-toggle" aria-expanded="false">
            <span class="ofac-sources-label"><?php esc_html_e( 'Sources', 'anythingllm-chatbot' ); ?></span>
            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
                <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
            </svg>
        </button>
        <ul class="ofac-sources-list" hidden></ul>
    </div>
</template>
