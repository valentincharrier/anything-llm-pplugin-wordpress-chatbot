/**
 * OFAC Chatbot Frontend JavaScript
 * 
 * @package Ocade_Fusion_AnythingLLM_Chatbot
 * @since 1.0.0
 */

(function() {
    'use strict';

    // Vérifier que la configuration est disponible
    if (typeof ofacConfig === 'undefined') {
        console.error('OFAC: Configuration not found');
        return;
    }

    /**
     * Classe principale du chatbot
     */
    class OFACChatbot {
        constructor() {
            this.config = ofacConfig;
            this.isOpen = false;
            this.isTyping = false;
            this.conversationHistory = [];
            this.sessionId = this.getOrCreateSessionId();
            this.hasConsent = false;
            this.focusTrap = null;
            this.lastFocusedElement = null;
            this.abortController = null;
            this.messageQueue = [];
            this.isProcessing = false;

            // Historique des commandes (flèche haut/bas)
            this.commandHistory = [];
            this.commandHistoryIndex = -1;
            this.currentInput = '';

            // Image en attente d'envoi
            this.pendingImage = null;

            // Éléments DOM
            this.elements = {};

            // Bindings
            this.handleKeyDown = this.handleKeyDown.bind(this);
            this.handleOutsideClick = this.handleOutsideClick.bind(this);
            this.handleResize = this.debounce(this.handleResize.bind(this), 250);

            // Initialisation
            this.init();
        }

        /**
         * Initialisation du chatbot
         */
        init() {
            // Attendre que le DOM soit prêt
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setup());
            } else {
                this.setup();
            }
        }

        /**
         * Configuration initiale
         */
        setup() {
            // Récupérer les éléments DOM
            this.cacheElements();

            if (!this.elements.container) {
                console.error('OFAC: Chatbot container not found');
                return;
            }

            // Charger l'historique depuis localStorage
            this.loadHistory();

            // Charger l'historique des commandes
            this.loadCommandHistory();

            // Vérifier le consentement
            this.checkConsent();

            // Attacher les événements
            this.bindEvents();

            // Vérifier prefers-reduced-motion
            this.checkReducedMotion();

            // Déclencher l'événement d'initialisation
            this.trigger('ofac:init', { chatbot: this });
        }

        /**
         * Mise en cache des éléments DOM
         */
        cacheElements() {
            this.elements = {
                container: document.getElementById('ofac-chatbot'),
                trigger: document.getElementById('ofac-trigger'),
                modal: document.getElementById('ofac-modal'),
                closeBtn: document.getElementById('ofac-close'),
                messagesContainer: document.getElementById('ofac-messages'),
                inputForm: document.getElementById('ofac-input-form'),
                inputField: document.getElementById('ofac-input'),
                sendBtn: document.getElementById('ofac-send'),
                fileInput: document.getElementById('ofac-file-input'),
                fileBtn: document.getElementById('ofac-file-btn'),
                typingIndicator: document.getElementById('ofac-typing'),
                consentDialog: document.getElementById('ofac-consent-screen'),
                consentAccept: document.getElementById('ofac-consent-accept'),
                consentDecline: document.getElementById('ofac-consent-decline'),
                quickReplies: document.getElementById('ofac-quick-replies'),
                exportBtn: document.getElementById('ofac-export'),
                resetBtn: document.getElementById('ofac-reset')
            };
        }

        /**
         * Attachement des événements
         */
        bindEvents() {
            // Bouton d'ouverture
            if (this.elements.trigger) {
                this.elements.trigger.addEventListener('click', () => this.open());
            }

            // Bouton de fermeture
            if (this.elements.closeBtn) {
                this.elements.closeBtn.addEventListener('click', () => this.close());
            }

            // Formulaire d'envoi
            if (this.elements.inputForm) {
                this.elements.inputForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.sendMessage();
                });
            }

            // Champ de saisie - auto-resize et historique
            if (this.elements.inputField) {
                this.elements.inputField.addEventListener('input', () => {
                    this.autoResizeInput();
                    this.updateSendButton();
                    // Reset l'index de l'historique quand on tape
                    this.commandHistoryIndex = -1;
                });

                this.elements.inputField.addEventListener('keydown', (e) => {
                    // Envoi avec Enter (sans Shift)
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.sendMessage();
                        return;
                    }

                    // Navigation dans l'historique avec flèche haut/bas
                    if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                        this.navigateHistory(e.key === 'ArrowUp' ? 'up' : 'down');
                        e.preventDefault();
                    }
                });
            }

            // Upload de fichiers
            if (this.elements.fileBtn && this.elements.fileInput) {
                this.elements.fileBtn.addEventListener('click', () => {
                    this.elements.fileInput.click();
                });

                this.elements.fileInput.addEventListener('change', (e) => {
                    this.handleFileUpload(e.target.files);
                });
            }

            // Consentement
            if (this.elements.consentAccept) {
                this.elements.consentAccept.addEventListener('click', () => {
                    this.acceptConsent();
                });
            }

            if (this.elements.consentDecline) {
                this.elements.consentDecline.addEventListener('click', () => {
                    this.declineConsent();
                });
            }

            // Export et Reset
            if (this.elements.exportBtn) {
                this.elements.exportBtn.addEventListener('click', () => this.exportConversation());
            }

            if (this.elements.resetBtn) {
                this.elements.resetBtn.addEventListener('click', () => this.resetConversation());
            }

            // Événements globaux
            document.addEventListener('keydown', this.handleKeyDown);
            window.addEventListener('resize', this.handleResize);

            // Délégation d'événements pour skip links et boutons d'accès rapide
            // Plus robuste car fonctionne même si les éléments sont ajoutés après l'init
            document.addEventListener('click', (e) => {
                const target = e.target.closest('.ofac-skip-link, [data-action="open-chatbot"], [href="#ofac-chatbot"]');
                if (target) {
                    e.preventDefault();
                    this.open();
                    setTimeout(() => this.focusInput(), 150);
                }
            });

            // Support clavier (Entrée et Espace) pour les liens d'accès rapide
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    const target = e.target.closest('.ofac-skip-link, [data-action="open-chatbot"], [href="#ofac-chatbot"]');
                    if (target) {
                        e.preventDefault();
                        this.open();
                        setTimeout(() => this.focusInput(), 150);
                    }
                }
            });
        }

        /**
         * Gestion des touches clavier
         */
        handleKeyDown(e) {
            // Échap pour fermer
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
            }

            // Tab pour focus trap
            if (e.key === 'Tab' && this.isOpen) {
                this.handleTabKey(e);
            }
        }

        /**
         * Focus trap dans la modal
         */
        handleTabKey(e) {
            if (!this.elements.modal) return;

            const focusableElements = this.elements.modal.querySelectorAll(
                'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            );

            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            if (e.shiftKey && document.activeElement === firstElement) {
                e.preventDefault();
                lastElement.focus();
            } else if (!e.shiftKey && document.activeElement === lastElement) {
                e.preventDefault();
                firstElement.focus();
            }
        }

        /**
         * Clic en dehors de la modal
         */
        handleOutsideClick(e) {
            if (this.elements.modal && !this.elements.modal.contains(e.target) && 
                this.elements.trigger && !this.elements.trigger.contains(e.target)) {
                this.close();
            }
        }

        /**
         * Gestion du redimensionnement
         */
        handleResize() {
            if (this.isOpen) {
                this.adjustModalPosition();
            }
        }

        /**
         * Ajustement de la position de la modal
         */
        adjustModalPosition() {
            if (!this.elements.modal) return;

            const isMobile = window.innerWidth < 768;
            this.elements.modal.classList.toggle('ofac-modal--mobile', isMobile);
        }

        /**
         * Vérification de prefers-reduced-motion
         */
        checkReducedMotion() {
            const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
            
            const updateMotion = () => {
                if (this.elements.container) {
                    this.elements.container.classList.toggle('ofac-reduced-motion', prefersReducedMotion.matches);
                }
            };

            updateMotion();
            prefersReducedMotion.addEventListener('change', updateMotion);
        }

        /**
         * Ouverture du chatbot
         */
        open() {
            if (this.isOpen) return;

            this.isOpen = true;
            this.lastFocusedElement = document.activeElement;

            if (this.elements.modal) {
                this.elements.modal.classList.add('ofac-modal--open');
                this.elements.modal.setAttribute('aria-hidden', 'false');
            }

            if (this.elements.trigger) {
                this.elements.trigger.setAttribute('aria-expanded', 'true');
            }

            // Ajuster la position
            this.adjustModalPosition();

            // Écouter les clics en dehors
            document.addEventListener('click', this.handleOutsideClick);

            // Vérifier le consentement - afficher le dialogue si nécessaire
            // Par défaut, le consentement est requis (RGPD) sauf si explicitement désactivé
            const requireConsent = this.config.settings.require_consent !== false;
            if (requireConsent && !this.hasConsent) {
                this.showConsentDialog();
                // Ne pas focus sur l'input, le dialogue de consentement va prendre le focus
                return;
            }

            // Focus sur le champ de saisie (seulement si consentement donné)
            setTimeout(() => this.focusInput(), 100);

            // Annoncer l'ouverture aux lecteurs d'écran
            this.announce(this.config.labels.chat_opened || 'Chat ouvert');

            this.trigger('ofac:open');
        }

        /**
         * Fermeture du chatbot
         */
        close() {
            if (!this.isOpen) return;

            this.isOpen = false;

            if (this.elements.modal) {
                this.elements.modal.classList.remove('ofac-modal--open');
                this.elements.modal.setAttribute('aria-hidden', 'true');
            }

            if (this.elements.trigger) {
                this.elements.trigger.setAttribute('aria-expanded', 'false');
            }

            // Restaurer le focus
            if (this.lastFocusedElement) {
                this.lastFocusedElement.focus();
            }

            // Retirer l'écouteur de clics
            document.removeEventListener('click', this.handleOutsideClick);

            // Annuler toute requête en cours
            if (this.abortController) {
                this.abortController.abort();
            }

            this.trigger('ofac:close');
        }

        /**
         * Focus sur le champ de saisie
         */
        focusInput() {
            if (this.elements.inputField) {
                this.elements.inputField.focus();
            }
        }

        /**
         * Affichage du dialogue de consentement
         */
        showConsentDialog() {
            if (this.elements.consentDialog) {
                this.elements.consentDialog.classList.add('ofac-consent--visible');
                this.elements.consentDialog.setAttribute('aria-hidden', 'false');
                
                if (this.elements.consentAccept) {
                    this.elements.consentAccept.focus();
                }
            }
        }

        /**
         * Masquage du dialogue de consentement
         */
        hideConsentDialog() {
            if (this.elements.consentDialog) {
                this.elements.consentDialog.classList.remove('ofac-consent--visible');
                this.elements.consentDialog.setAttribute('aria-hidden', 'true');
            }
        }

        /**
         * Acceptation du consentement
         */
        acceptConsent() {
            this.hasConsent = true;
            this.hideConsentDialog();

            // Enregistrer le consentement côté serveur
            this.recordConsent(true);

            // Réactiver la zone de saisie
            this.enableInputArea();

            // Ouvrir le chatbot
            this.open();
        }

        /**
         * Refus du consentement
         */
        declineConsent() {
            this.hasConsent = false;
            this.hideConsentDialog();
            this.recordConsent(false);

            // Fermer le chatbot
            this.close();

            // Désactiver l'input et le bouton d'envoi
            this.disableInputArea();
        }

        /**
         * Désactiver la zone de saisie (après refus de consentement)
         */
        disableInputArea() {
            if (this.elements.inputField) {
                this.elements.inputField.disabled = true;
                this.elements.inputField.placeholder = this.config.labels.consent_required || 'Consentement requis pour utiliser le chat';
            }
            if (this.elements.sendBtn) {
                this.elements.sendBtn.disabled = true;
            }
            if (this.elements.fileBtn) {
                this.elements.fileBtn.disabled = true;
            }
        }

        /**
         * Réactiver la zone de saisie (après acceptation du consentement)
         */
        enableInputArea() {
            if (this.elements.inputField) {
                this.elements.inputField.disabled = false;
                this.elements.inputField.placeholder = this.config.settings.placeholder || 'Tapez votre message...';
            }
            if (this.elements.sendBtn) {
                this.elements.sendBtn.disabled = false;
            }
            if (this.elements.fileBtn) {
                this.elements.fileBtn.disabled = false;
            }
        }

        /**
         * Enregistrement du consentement
         */
        recordConsent(accepted) {
            const formData = new FormData();
            formData.append('action', 'ofac_set_consent');
            formData.append('nonce', this.config.nonce);
            formData.append('consent', accepted ? '1' : '0');
            formData.append('session_id', this.sessionId);

            fetch(this.config.ajax_url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).catch(error => {
                console.error('OFAC: Error recording consent', error);
            });
        }

        /**
         * Vérification du consentement
         */
        checkConsent() {
            // Vérifier le cookie de consentement (format JSON)
            const consentCookie = this.getCookie('ofac_consent');

            if (consentCookie) {
                try {
                    const consentData = JSON.parse(decodeURIComponent(consentCookie));
                    // Vérifier si consented est true et si le cookie n'est pas expiré
                    if (consentData.consented === true && consentData.expires > Date.now() / 1000) {
                        this.hasConsent = true;
                        return;
                    }
                } catch (e) {
                    // Cookie mal formé, considérer comme pas de consentement
                    console.warn('OFAC: Invalid consent cookie format');
                }
            }

            this.hasConsent = false;
        }

        /**
         * Envoi d'un message
         */
        async sendMessage(message = null) {
            // Vérifier le consentement avant tout envoi
            // Par défaut, le consentement est requis (RGPD) sauf si explicitement désactivé
            const requireConsent = this.config.settings.require_consent !== false;
            if (requireConsent && !this.hasConsent) {
                this.showError(this.config.labels.consent_required || 'Vous devez accepter les conditions pour utiliser le chat.');
                return;
            }

            const text = message || (this.elements.inputField ? this.elements.inputField.value.trim() : '');

            // Permettre l'envoi si on a du texte OU une image en attente
            if ((!text && !this.pendingImage) || this.isProcessing) return;

            // Vérifier les commandes spéciales
            if (text && this.isCommand(text)) {
                this.processCommand(text);
                this.clearInput();
                return;
            }

            // Vérifier la longueur du message
            const maxLength = this.config.settings.max_message_length || 2000;
            if (text && text.length > maxLength) {
                this.showError(this.config.labels.message_too_long || `Message trop long (max ${maxLength} caractères)`);
                return;
            }

            // Ajouter à l'historique des commandes
            if (text) {
                this.addToHistory(text);
            }

            this.isProcessing = true;
            this.disableSendButton();
            this.clearInput();

            // Capturer l'image en attente avant de la supprimer
            const imageToSend = this.pendingImage;
            
            // Ajouter le message utilisateur (avec preview image si présente)
            if (imageToSend) {
                // Afficher l'image dans le message
                const imageHtml = `<div class="ofac-message-image"><img src="${imageToSend.preview}" alt="${imageToSend.name}" /></div>`;
                this.addMessage('user', text ? `${imageHtml}<p>${text}</p>` : imageHtml, { isHtml: true });
                this.removeImagePreview();
            } else {
                this.addMessage('user', text);
            }

            // Afficher l'indicateur de frappe
            this.showTyping();

            try {
                if (this.config.stream_enabled) {
                    await this.sendStreamingMessage(text, imageToSend);
                } else {
                    await this.sendStandardMessage(text, imageToSend);
                }
            } catch (error) {
                this.hideTyping();
                if (error.name !== 'AbortError') {
                    this.showError(error.message || this.config.labels.error_message);
                }
            } finally {
                this.isProcessing = false;
                this.enableSendButton();
            }
        }

        /**
         * Désactiver le bouton d'envoi
         */
        disableSendButton() {
            if (this.elements.sendBtn) {
                this.elements.sendBtn.disabled = true;
                this.elements.sendBtn.classList.add('ofac-loading');
            }
            if (this.elements.inputField) {
                this.elements.inputField.disabled = true;
            }
        }

        /**
         * Réactiver le bouton d'envoi
         */
        enableSendButton() {
            if (this.elements.sendBtn) {
                this.elements.sendBtn.disabled = false;
                this.elements.sendBtn.classList.remove('ofac-loading');
            }
            if (this.elements.inputField) {
                this.elements.inputField.disabled = false;
                this.elements.inputField.focus();
            }
        }

        /**
         * Ajouter une commande à l'historique
         */
        addToHistory(text) {
            if (!text || text.trim() === '') return;
            
            // Éviter les doublons consécutifs
            if (this.commandHistory[0] === text) return;
            
            // Ajouter au début
            this.commandHistory.unshift(text);
            
            // Limiter à 50 commandes
            if (this.commandHistory.length > 50) {
                this.commandHistory.pop();
            }
            
            // Sauvegarder dans localStorage
            try {
                localStorage.setItem('ofac_command_history', JSON.stringify(this.commandHistory));
            } catch (e) {
                // Ignore storage errors
            }
            
            // Reset l'index
            this.commandHistoryIndex = -1;
        }

        /**
         * Charger l'historique des commandes
         */
        loadCommandHistory() {
            try {
                const saved = localStorage.getItem('ofac_command_history');
                if (saved) {
                    this.commandHistory = JSON.parse(saved);
                }
            } catch (e) {
                this.commandHistory = [];
            }
        }

        /**
         * Naviguer dans l'historique des commandes (flèche haut/bas)
         */
        navigateHistory(direction) {
            if (!this.elements.inputField) return;
            
            if (this.commandHistory.length === 0) return;

            // Sauvegarder l'input courant si on commence la navigation
            if (this.commandHistoryIndex === -1 && direction === 'up') {
                this.currentInput = this.elements.inputField.value;
            }

            if (direction === 'up') {
                // Remonter dans l'historique
                if (this.commandHistoryIndex < this.commandHistory.length - 1) {
                    this.commandHistoryIndex++;
                    this.elements.inputField.value = this.commandHistory[this.commandHistoryIndex];
                }
            } else {
                // Descendre dans l'historique
                if (this.commandHistoryIndex > 0) {
                    this.commandHistoryIndex--;
                    this.elements.inputField.value = this.commandHistory[this.commandHistoryIndex];
                } else if (this.commandHistoryIndex === 0) {
                    // Revenir à l'input original
                    this.commandHistoryIndex = -1;
                    this.elements.inputField.value = this.currentInput;
                }
            }

            // Placer le curseur à la fin
            this.elements.inputField.setSelectionRange(
                this.elements.inputField.value.length,
                this.elements.inputField.value.length
            );

            // Mettre à jour le bouton d'envoi
            this.updateSendButton();
        }

        /**
         * Envoi standard (non-streaming)
         */
        async sendStandardMessage(text, image = null) {
            const formData = new FormData();
            formData.append('action', 'ofac_chat');
            formData.append('nonce', this.config.nonce);
            formData.append('message', text || '');
            formData.append('session_id', this.sessionId);

            // Ajouter l'image en base64 si présente
            if (image && image.base64) {
                formData.append('image_data', image.base64);
                formData.append('image_name', image.name);
                formData.append('image_mime', image.mime);
            }

            // Ajouter le honeypot
            formData.append('ofac_hp', '');

            this.abortController = new AbortController();

            const response = await fetch(this.config.ajax_url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                signal: this.abortController.signal
            });

            const data = await response.json();

            this.hideTyping();

            if (data.success) {
                // Support both 'text' (AnythingLLM) and 'response' (fallback)
                const responseText = data.data.text || data.data.response || '';
                
                this.addMessage('assistant', responseText, {
                    sources: data.data.sources,
                    suggestions: data.data.suggestions
                });

                if (data.data.suggestions && data.data.suggestions.length) {
                    this.showQuickReplies(data.data.suggestions);
                }
            } else {
                throw new Error(data.data?.message || this.config.labels.error_message);
            }
        }

        /**
         * Envoi en streaming (SSE)
         */
        async sendStreamingMessage(text, image = null) {
            // Note: Le streaming avec image n'est pas supporté, on utilise le mode standard
            if (image) {
                return this.sendStandardMessage(text, image);
            }

            const params = new URLSearchParams({
                action: 'ofac_chat_stream',
                nonce: this.config.nonce,
                message: text,
                session_id: this.sessionId
            });

            this.abortController = new AbortController();

            const response = await fetch(`${this.config.ajax_url}?${params}`, {
                method: 'GET',
                credentials: 'same-origin',
                signal: this.abortController.signal
            });

            if (!response.ok) {
                throw new Error(`HTTP error: ${response.status}`);
            }

            this.hideTyping();

            // Créer le message assistant vide
            const messageId = this.addMessage('assistant', '', { streaming: true });
            const messageElement = document.getElementById(messageId);
            const contentElement = messageElement?.querySelector('.ofac-message-bubble');

            if (!contentElement) return;

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let fullContent = '';
            let buffer = '';

            try {
                while (true) {
                    const { done, value } = await reader.read();

                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });

                    // Traiter les événements SSE
                    const lines = buffer.split('\n');
                    buffer = lines.pop() || '';

                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            const data = line.slice(6);

                            if (data === '[DONE]') {
                                // Streaming terminé
                                this.updateMessageContent(contentElement, fullContent, false);
                                this.saveHistory();
                                break;
                            }

                            try {
                                const parsed = JSON.parse(data);
                                if (parsed.textResponse) {
                                    fullContent += parsed.textResponse;
                                    this.updateMessageContent(contentElement, fullContent, true);
                                }

                                if (parsed.sources) {
                                    this.addSourcesToMessage(messageElement, parsed.sources);
                                }

                                if (parsed.suggestedQuestions) {
                                    this.showQuickReplies(parsed.suggestedQuestions);
                                }
                            } catch (e) {
                                // Ignorer les erreurs de parsing
                            }
                        }
                    }
                }
            } finally {
                reader.releaseLock();
            }

            // Mettre à jour l'historique
            const messageIndex = this.conversationHistory.findIndex(m => m.id === messageId);
            if (messageIndex !== -1) {
                this.conversationHistory[messageIndex].content = fullContent;
            }
        }

        /**
         * Mise à jour du contenu d'un message (avec Markdown)
         */
        updateMessageContent(element, content, isStreaming = false) {
            if (!element) return;

            // Convertir le Markdown en HTML
            const html = this.parseMarkdown(content);
            element.innerHTML = html;

            // Coloration syntaxique
            if (!isStreaming) {
                this.highlightCode(element);
            }

            // Scroll vers le bas
            this.scrollToBottom();
        }

        /**
         * Parser Markdown basique
         */
        parseMarkdown(text) {
            if (!text) return '';

            let html = this.escapeHtml(text);

            // Code blocks
            html = html.replace(/```(\w*)\n([\s\S]*?)```/g, (match, lang, code) => {
                const language = lang || 'plaintext';
                return `<pre class="ofac-code-block" data-language="${language}"><code class="language-${language}">${code.trim()}</code></pre>`;
            });

            // Inline code
            html = html.replace(/`([^`]+)`/g, '<code class="ofac-inline-code">$1</code>');

            // Bold
            html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');

            // Italic
            html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');

            // Links
            html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');

            // Line breaks
            html = html.replace(/\n/g, '<br>');

            return html;
        }

        /**
         * Coloration syntaxique
         */
        highlightCode(container) {
            const codeBlocks = container.querySelectorAll('pre code');
            codeBlocks.forEach(block => {
                // Utiliser Prism.js si disponible
                if (typeof Prism !== 'undefined') {
                    Prism.highlightElement(block);
                }
            });
        }

        /**
         * Ajout d'un message dans le chat
         */
        addMessage(role, content, options = {}) {
            const id = `ofac-msg-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
            const timestamp = new Date().toISOString();

            // Ajouter à l'historique
            const message = {
                id,
                role,
                content,
                timestamp,
                ...options
            };
            this.conversationHistory.push(message);

            // Créer l'élément DOM
            const messageElement = this.createMessageElement(message);
            
            if (this.elements.messagesContainer) {
                this.elements.messagesContainer.appendChild(messageElement);
            }

            // Sauvegarder dans localStorage
            if (!options.streaming) {
                this.saveHistory();
            }

            // Scroll vers le bas
            this.scrollToBottom();

            // Annoncer aux lecteurs d'écran
            if (role === 'assistant') {
                this.announce(this.config.labels.new_message || 'Nouveau message');
            }

            return id;
        }

        /**
         * Création de l'élément DOM d'un message
         */
        createMessageElement(message) {
            const div = document.createElement('div');
            div.id = message.id;
            div.className = `ofac-message ofac-message--${message.role}`;
            div.setAttribute('role', 'article');

            // Avatar with fallback
            const avatarUrl = message.role === 'user' 
                ? this.config.settings.user_avatar 
                : this.config.settings.bot_avatar;

            // Default avatar SVG
            const defaultAvatarSvg = `<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
            </svg>`;

            const avatarHtml = avatarUrl 
                ? `<img src="${avatarUrl}" alt="" class="ofac-message-avatar" aria-hidden="true" 
                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                   <div class="ofac-message-avatar ofac-avatar-default" aria-hidden="true" style="display:none;">${defaultAvatarSvg}</div>`
                : `<div class="ofac-message-avatar ofac-avatar-default" aria-hidden="true">${defaultAvatarSvg}</div>`;

            // Contenu - Si isHtml, ne pas parser le markdown
            const contentHtml = message.isHtml ? message.content : this.parseMarkdown(message.content);

            // Timestamp formaté
            const timeFormatted = new Date(message.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            div.innerHTML = `
                ${avatarHtml}
                <div class="ofac-message-content">
                    <div class="ofac-message-bubble">${contentHtml}</div>
                    <div class="ofac-message__meta">
                        <time class="ofac-message__time" datetime="${message.timestamp}">${timeFormatted}</time>
                        ${message.role === 'assistant' ? this.createMessageActions(message.id) : ''}
                    </div>
                    ${message.sources ? this.createSourcesHtml(message.sources) : ''}
                </div>
            `;

            // Ajouter les événements pour les actions
            if (message.role === 'assistant') {
                this.bindMessageActions(div, message);
            }

            return div;
        }

        /**
         * Création des actions de message
         */
        createMessageActions(messageId) {
            return `
                <div class="ofac-message__actions">
                    <button type="button" class="ofac-message__action ofac-message__action--copy" 
                            data-action="copy" data-message-id="${messageId}"
                            aria-label="${this.config.labels.copy || 'Copier'}">
                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                        </svg>
                    </button>
                    <button type="button" class="ofac-message__action ofac-message__action--thumbup" 
                            data-action="thumbup" data-message-id="${messageId}"
                            aria-label="${this.config.labels.helpful || 'Utile'}">
                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                        </svg>
                    </button>
                    <button type="button" class="ofac-message__action ofac-message__action--thumbdown" 
                            data-action="thumbdown" data-message-id="${messageId}"
                            aria-label="${this.config.labels.not_helpful || 'Pas utile'}">
                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"></path>
                        </svg>
                    </button>
                </div>
            `;
        }

        /**
         * Attachement des événements aux actions de message
         */
        bindMessageActions(element, message) {
            const copyBtn = element.querySelector('[data-action="copy"]');
            const thumbUpBtn = element.querySelector('[data-action="thumbup"]');
            const thumbDownBtn = element.querySelector('[data-action="thumbdown"]');

            if (copyBtn) {
                copyBtn.addEventListener('click', () => this.copyMessage(message));
            }

            if (thumbUpBtn) {
                thumbUpBtn.addEventListener('click', (e) => this.rateMessage(message.id, 1, e.currentTarget));
            }

            if (thumbDownBtn) {
                thumbDownBtn.addEventListener('click', (e) => this.rateMessage(message.id, -1, e.currentTarget));
            }
        }

        /**
         * Copie d'un message
         */
        async copyMessage(message) {
            try {
                await navigator.clipboard.writeText(message.content);
                this.showToast(this.config.labels.copied || 'Copié !');
            } catch (error) {
                console.error('OFAC: Copy failed', error);
            }
        }

        /**
         * Notation d'un message
         */
        async rateMessage(messageId, rating, button) {
            const formData = new FormData();
            formData.append('action', 'ofac_feedback');
            formData.append('nonce', this.config.nonce);
            formData.append('message_id', messageId);
            formData.append('rating', rating);

            try {
                const response = await fetch(this.config.ajax_url, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const data = await response.json();

                if (data.success) {
                    // Mettre en surbrillance le bouton sélectionné
                    const parent = button.closest('.ofac-message__actions');
                    if (parent) {
                        parent.querySelectorAll('.ofac-message__action').forEach(btn => {
                            btn.classList.remove('ofac-message__action--active');
                        });
                        button.classList.add('ofac-message__action--active');
                    }
                }
            } catch (error) {
                console.error('OFAC: Feedback error', error);
            }
        }

        /**
         * Création du HTML des sources
         */
        createSourcesHtml(sources) {
            if (!sources || !sources.length) return '';

            const sourcesList = sources.map((source, index) => {
                const title = source.title || `Source ${index + 1}`;
                const url = source.url || '#';
                return `<a href="${url}" target="_blank" rel="noopener noreferrer" class="ofac-source">${this.escapeHtml(title)}</a>`;
            }).join('');

            return `<div class="ofac-message__sources">${sourcesList}</div>`;
        }

        /**
         * Ajout des sources à un message existant
         */
        addSourcesToMessage(messageElement, sources) {
            if (!messageElement || !sources || !sources.length) return;

            const existingSources = messageElement.querySelector('.ofac-message__sources');
            if (existingSources) {
                existingSources.remove();
            }

            const body = messageElement.querySelector('.ofac-message__body');
            if (body) {
                body.insertAdjacentHTML('beforeend', this.createSourcesHtml(sources));
            }
        }

        /**
         * Affichage des quick replies
         */
        showQuickReplies(suggestions) {
            if (!this.elements.quickReplies || !suggestions || !suggestions.length) return;

            const html = suggestions.map(suggestion => {
                return `<button type="button" class="ofac-quick-reply">${this.escapeHtml(suggestion)}</button>`;
            }).join('');

            this.elements.quickReplies.innerHTML = html;
            this.elements.quickReplies.classList.add('ofac-quick-replies--visible');

            // Attacher les événements
            this.elements.quickReplies.querySelectorAll('.ofac-quick-reply').forEach(btn => {
                btn.addEventListener('click', () => {
                    this.sendMessage(btn.textContent);
                    this.hideQuickReplies();
                });
            });
        }

        /**
         * Masquage des quick replies
         */
        hideQuickReplies() {
            if (this.elements.quickReplies) {
                this.elements.quickReplies.classList.remove('ofac-quick-replies--visible');
                this.elements.quickReplies.innerHTML = '';
            }
        }

        /**
         * Affichage de l'indicateur de frappe
         */
        showTyping() {
            this.isTyping = true;
            if (this.elements.typingIndicator) {
                this.elements.typingIndicator.classList.add('ofac-typing--visible');
                this.elements.typingIndicator.setAttribute('aria-hidden', 'false');
            }
            this.scrollToBottom();
        }

        /**
         * Masquage de l'indicateur de frappe
         */
        hideTyping() {
            this.isTyping = false;
            if (this.elements.typingIndicator) {
                this.elements.typingIndicator.classList.remove('ofac-typing--visible');
                this.elements.typingIndicator.setAttribute('aria-hidden', 'true');
            }
        }

        /**
         * Scroll vers le bas du container de messages
         */
        scrollToBottom() {
            if (this.elements.messagesContainer) {
                this.elements.messagesContainer.scrollTop = this.elements.messagesContainer.scrollHeight;
            }
        }

        /**
         * Effacement du champ de saisie
         */
        clearInput() {
            if (this.elements.inputField) {
                this.elements.inputField.value = '';
                this.autoResizeInput();
                this.updateSendButton();
            }
        }

        /**
         * Auto-resize du champ de saisie
         */
        autoResizeInput() {
            if (!this.elements.inputField) return;

            this.elements.inputField.style.height = 'auto';
            this.elements.inputField.style.height = Math.min(this.elements.inputField.scrollHeight, 150) + 'px';
        }

        /**
         * Mise à jour du bouton d'envoi
         */
        updateSendButton() {
            if (!this.elements.sendBtn || !this.elements.inputField) return;

            const hasContent = this.elements.inputField.value.trim().length > 0;
            this.elements.sendBtn.disabled = !hasContent || this.isProcessing;
        }

        /**
         * Vérification si le texte est une commande
         */
        isCommand(text) {
            return text.startsWith('/');
        }

        /**
         * Traitement des commandes
         */
        processCommand(text) {
            const command = text.toLowerCase().split(' ')[0];
            const args = text.slice(command.length).trim();

            switch (command) {
                case '/reset':
                    this.resetConversation();
                    break;
                case '/help':
                    this.showHelp();
                    break;
                case '/export':
                    this.exportConversation();
                    break;
                default:
                    this.addMessage('system', this.config.labels.unknown_command || `Commande inconnue: ${command}`);
            }
        }

        /**
         * Affichage de l'aide
         */
        showHelp() {
            const helpText = `**Commandes disponibles:**
- \`/reset\` - Effacer la conversation
- \`/help\` - Afficher cette aide
- \`/export\` - Exporter la conversation`;

            this.addMessage('system', helpText);
        }

        /**
         * Réinitialisation de la conversation
         */
        resetConversation() {
            if (confirm(this.config.labels.confirm_reset || 'Voulez-vous vraiment effacer la conversation ?')) {
                // Vider l'historique
                this.conversationHistory = [];
                this.saveHistory();

                // Vider le container de messages
                if (this.elements.messagesContainer) {
                    this.elements.messagesContainer.innerHTML = '';
                }

                // Masquer les quick replies
                this.hideQuickReplies();

                // Générer un nouveau session ID
                this.sessionId = this.generateSessionId();
                sessionStorage.setItem('ofac_session_id', this.sessionId);

                // Afficher un message de confirmation
                this.addMessage('system', this.config.labels.conversation_reset || 'Conversation effacée.');

                this.trigger('ofac:reset');
            }
        }

        /**
         * Export de la conversation
         */
        exportConversation() {
            if (!this.conversationHistory.length) {
                this.showToast(this.config.labels.nothing_to_export || 'Rien à exporter');
                return;
            }

            let text = `Conversation - ${new Date().toLocaleDateString()}\n${'='.repeat(40)}\n\n`;

            this.conversationHistory.forEach(message => {
                const time = new Date(message.timestamp).toLocaleTimeString();
                const role = message.role === 'user' ? 'Vous' : 'Assistant';
                text += `[${time}] ${role}:\n${message.content}\n\n`;
            });

            // Créer et télécharger le fichier
            const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `conversation-${Date.now()}.txt`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            this.showToast(this.config.labels.exported || 'Conversation exportée');
            this.trigger('ofac:export');
        }

        /**
         * Gestion de l'upload de fichiers
         */
        async handleFileUpload(files) {
            if (!files || !files.length) return;

            const maxSize = (this.config.settings.max_file_size || 5) * 1024 * 1024;
            const allowedTypes = this.config.settings.allowed_file_types || ['pdf', 'txt', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
            const imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            for (const file of files) {
                // Vérifier la taille
                if (file.size > maxSize) {
                    this.showError(`${file.name}: ${this.config.labels.file_too_large || 'Fichier trop volumineux'}`);
                    continue;
                }

                // Vérifier le type
                const ext = file.name.split('.').pop().toLowerCase();
                if (!allowedTypes.includes(ext)) {
                    this.showError(`${file.name}: ${this.config.labels.file_type_not_allowed || 'Type de fichier non autorisé'}`);
                    continue;
                }

                // Si c'est une image, la convertir en base64 et la stocker
                if (imageTypes.includes(ext)) {
                    await this.prepareImageForChat(file);
                } else {
                    // Pour les autres fichiers, uploader normalement
                    await this.uploadFile(file);
                }
            }

            // Réinitialiser l'input
            if (this.elements.fileInput) {
                this.elements.fileInput.value = '';
            }
        }

        /**
         * Préparer une image pour l'envoi dans le chat
         */
        async prepareImageForChat(file) {
            try {
                const base64 = await this.fileToBase64(file);
                
                // Stocker l'image en attente
                this.pendingImage = {
                    name: file.name,
                    mime: file.type,
                    base64: base64,
                    preview: URL.createObjectURL(file)
                };

                // Afficher le preview dans l'input
                this.showImagePreview();
                
                // Focus sur l'input pour que l'utilisateur puisse poser sa question
                this.focusInput();
                
            } catch (error) {
                this.showError(error.message || 'Erreur lors du chargement de l\'image');
            }
        }

        /**
         * Convertir un fichier en base64
         */
        fileToBase64(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = () => reject(new Error('Erreur de lecture du fichier'));
                reader.readAsDataURL(file);
            });
        }

        /**
         * Afficher le preview de l'image en attente
         */
        showImagePreview() {
            if (!this.pendingImage) return;

            // Créer ou récupérer le conteneur de preview
            let previewContainer = document.getElementById('ofac-image-preview');
            if (!previewContainer) {
                previewContainer = document.createElement('div');
                previewContainer.id = 'ofac-image-preview';
                previewContainer.className = 'ofac-image-preview';
                
                // Insérer avant le formulaire
                const inputForm = this.elements.inputForm;
                if (inputForm && inputForm.parentNode) {
                    inputForm.parentNode.insertBefore(previewContainer, inputForm);
                }
            }

            previewContainer.innerHTML = `
                <div class="ofac-image-preview-content">
                    <img src="${this.pendingImage.preview}" alt="Preview" />
                    <span class="ofac-image-preview-name">${this.pendingImage.name}</span>
                    <button type="button" class="ofac-image-preview-remove" aria-label="Supprimer l'image">×</button>
                </div>
                <span class="ofac-image-preview-hint">Posez votre question sur cette image</span>
            `;

            // Ajouter l'event pour supprimer
            const removeBtn = previewContainer.querySelector('.ofac-image-preview-remove');
            if (removeBtn) {
                removeBtn.addEventListener('click', () => this.removeImagePreview());
            }

            previewContainer.style.display = 'flex';
        }

        /**
         * Supprimer le preview de l'image
         */
        removeImagePreview() {
            if (this.pendingImage && this.pendingImage.preview) {
                URL.revokeObjectURL(this.pendingImage.preview);
            }
            this.pendingImage = null;

            const previewContainer = document.getElementById('ofac-image-preview');
            if (previewContainer) {
                previewContainer.style.display = 'none';
                previewContainer.innerHTML = '';
            }
        }

        /**
         * Upload d'un fichier (non-image)
         */
        async uploadFile(file) {
            this.showTyping();

            const formData = new FormData();
            formData.append('action', 'ofac_upload_file');
            formData.append('nonce', this.config.nonce);
            formData.append('file', file);
            formData.append('session_id', this.sessionId);

            try {
                const response = await fetch(this.config.ajax_url, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const data = await response.json();

                this.hideTyping();

                if (data.success) {
                    this.addMessage('user', `📎 ${file.name}`);
                    this.showToast(data.data.message || this.config.labels.file_uploaded || 'Fichier uploadé');
                } else {
                    throw new Error(data.data?.message || this.config.labels.upload_error);
                }
            } catch (error) {
                this.hideTyping();
                this.showError(error.message);
            }
        }

        /**
         * Sauvegarde de l'historique dans localStorage
         */
        saveHistory() {
            try {
                const data = {
                    sessionId: this.sessionId,
                    messages: this.conversationHistory,
                    timestamp: Date.now()
                };
                localStorage.setItem('ofac_history', JSON.stringify(data));
            } catch (error) {
                console.error('OFAC: Failed to save history', error);
            }
        }

        /**
         * Chargement de l'historique depuis localStorage
         */
        loadHistory() {
            try {
                const stored = localStorage.getItem('ofac_history');
                if (!stored) return;

                const data = JSON.parse(stored);

                // Vérifier si les données sont encore valides (24h par défaut)
                const maxAge = 24 * 60 * 60 * 1000;
                if (Date.now() - data.timestamp > maxAge) {
                    localStorage.removeItem('ofac_history');
                    return;
                }

                // Restaurer la session
                if (data.sessionId) {
                    this.sessionId = data.sessionId;
                }

                // Restaurer les messages
                if (data.messages && data.messages.length) {
                    data.messages.forEach(message => {
                        this.conversationHistory.push(message);
                        const element = this.createMessageElement(message);
                        if (this.elements.messagesContainer) {
                            this.elements.messagesContainer.appendChild(element);
                        }
                    });
                    this.scrollToBottom();
                }
            } catch (error) {
                console.error('OFAC: Failed to load history', error);
            }
        }

        /**
         * Obtention ou création du session ID
         */
        getOrCreateSessionId() {
            let sessionId = sessionStorage.getItem('ofac_session_id');
            if (!sessionId) {
                sessionId = this.generateSessionId();
                sessionStorage.setItem('ofac_session_id', sessionId);
            }
            return sessionId;
        }

        /**
         * Génération d'un UUID v4
         */
        generateSessionId() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                const r = Math.random() * 16 | 0;
                const v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }

        /**
         * Affichage d'une erreur
         */
        showError(message) {
            this.addMessage('system', `⚠️ ${message}`);
        }

        /**
         * Affichage d'un toast
         */
        showToast(message) {
            const toast = document.createElement('div');
            toast.className = 'ofac-toast';
            toast.textContent = message;
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');

            document.body.appendChild(toast);

            // Animation d'entrée
            requestAnimationFrame(() => {
                toast.classList.add('ofac-toast--visible');
            });

            // Suppression après 3 secondes
            setTimeout(() => {
                toast.classList.remove('ofac-toast--visible');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        /**
         * Annonce pour les lecteurs d'écran
         */
        announce(message) {
            const announcer = document.getElementById('ofac-announcer');
            if (announcer) {
                announcer.textContent = message;
            }
        }

        /**
         * Récupération d'un cookie
         */
        getCookie(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) {
                return parts.pop().split(';').shift();
            }
            return null;
        }

        /**
         * Échappement HTML
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * Debounce
         */
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        /**
         * Déclenchement d'un événement personnalisé
         */
        trigger(eventName, detail = {}) {
            const event = new CustomEvent(eventName, { detail });
            document.dispatchEvent(event);
        }
    }

    // Initialisation automatique
    window.OFACChatbot = new OFACChatbot();

})();
