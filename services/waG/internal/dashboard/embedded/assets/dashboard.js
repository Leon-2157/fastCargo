function gatewayApp() {
            return {
                authenticated: false,
                tab: 'device',
                loginPassword: '',
                loginError: '',
                loading: false,

                // Status
                status: { status: 'disconnected', qr: null, user: null },
                statusTimer: null,

                // Config
                config: {},
                savedConfig: {},
                knowledgeBaseContent: '',
                savedKnowledgeBaseContent: '',

                // Pairing
                pairingPhone: '',
                pairingCodeResult: '',
                pairLoading: false,

                // Simulator
                simInput: '',
                simLoading: false,
                simChatHistory: [
                    { id: 1, role: 'assistant', text: 'Halo! Saya asisten virtual resmi. Ada yang bisa saya bantu?' }
                ],

                // Test Msg
                testMsg: { phone: '', type: 'otp', message: '' },
                testMsgLoading: false,

                // Stats & Logs
                stats: {},
                logs: [],

                // Password change
                passChange: { old: '', new: '', confirm: '' },

                // Toast
                toast: { show: false, message: '', type: 'success' },

                init() {
                    this.verifyToken();
                },

                showToast(message, type = 'success') {
                    this.toast = { show: true, message, type };
                    setTimeout(() => { this.toast.show = false; }, 4000);
                },

                async login() {
                    this.loading = true;
                    this.loginError = '';
                    try {
                        const res = await fetch('/api/dashboard/login', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ password: this.loginPassword })
                        });
                        const data = await res.json();
                        if (res.ok && data.success) {
                            this.authenticated = true;
                            this.loginPassword = '';
                            this.startApp();
                        } else {
                            this.loginError = data.error || 'Kata sandi salah';
                        }
                    } catch (e) {
                        this.loginError = 'Gagal menghubungi server';
                    } finally {
                        this.loading = false;
                    }
                },

                async verifyToken() {
                    try {
                        const res = await fetch('/api/dashboard/status');
                        if (res.ok) {
                            this.authenticated = true;
                            this.startApp();
                        } else {
                            this.authenticated = false;
                        }
                    } catch (e) {
                        this.authenticated = false;
                    }
                },

                async logout() {
                    try { await fetch('/api/dashboard/logout', { method: 'POST' }); } catch (e) {}
                    this.authenticated = false;
                    if (this.statusTimer) clearInterval(this.statusTimer);
                },

                startApp() {
                    this.fetchStatus();
                    this.fetchConfig();
                    this.fetchKnowledgeBase();
                    this.loadLogs();

                    if (this.statusTimer) clearInterval(this.statusTimer);
                    this.statusTimer = setInterval(() => {
                        this.fetchStatus();
                    }, 4000);
                },

                async fetchStatus() {
                    try {
                        const res = await fetch('/api/dashboard/status');
                        if (res.ok) {
                            this.status = await res.json();
                        }
                    } catch (e) {}
                },

                switchTab(targetTab) {
                    if (this.tab === 'ai' && targetTab !== 'ai' && this.hasAiChanges()) {
                        // Revert unsaved AI changes when leaving the tab
                        this.cancelAiChanges();
                        this.showToast('Perubahan yang belum disimpan telah dibatalkan.', 'info');
                    }
                    this.tab = targetTab;
                },

                hasAiChanges() {
                    if (!this.savedConfig) return false;
                    const fields = ['ai_enabled', 'ai_base_url', 'ai_api_key', 'ai_model', 'ai_fallback_message', 'ai_typing_delay_enabled', 'ai_typing_delay_value', 'ai_typing_delay_unit', 'ai_human_takeover_minutes'];
                    for (let f of fields) {
                        if (this.config[f] !== this.savedConfig[f]) return true;
                    }
                    return false;
                },

                cancelAiChanges() {
                    if (this.savedConfig) {
                        this.config = JSON.parse(JSON.stringify(this.savedConfig));
                    }
                },

                async fetchConfig() {
                    try {
                        const res = await fetch('/api/dashboard/config');
                        if (res.ok) {
                            this.config = await res.json();
                            this.savedConfig = JSON.parse(JSON.stringify(this.config));
                        }
                    } catch (e) {}
                },

                async fetchKnowledgeBase() {
                    try {
                        const res = await fetch('/api/dashboard/knowledge-base');
                        if (res.ok) {
                            const data = await res.json();
                            this.knowledgeBaseContent = data.content || '';
                            this.savedKnowledgeBaseContent = this.knowledgeBaseContent;
                        }
                    } catch (e) {}
                },

                async saveSettings() {
                    try {
                        const res = await fetch('/api/dashboard/config', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(this.config)
                        });
                        const data = await res.json();
                        if (res.ok && data.success) {
                            await this.fetchConfig();
                            this.showToast('Pengaturan berhasil disimpan!');
                        } else {
                            this.showToast(data.error || 'Gagal menyimpan pengaturan', 'error');
                        }
                    } catch (e) {
                        this.showToast('Error koneksi', 'error');
                    }
                },

                async saveKnowledgeBase() {
                    try {
                        const res = await fetch('/api/dashboard/knowledge-base', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ content: this.knowledgeBaseContent })
                        });
                        const data = await res.json();
                        if (res.ok && data.success) {
                            this.showToast('Basis pengetahuan berhasil diperbarui!');
                        } else {
                            this.showToast(data.error || 'Gagal menyimpan', 'error');
                        }
                    } catch (e) {
                        this.showToast('Error koneksi', 'error');
                    }
                },

                async requestPairCode() {
                    this.pairLoading = true;
                    this.pairingCodeResult = '';
                    try {
                        const res = await fetch('/api/dashboard/pairing-code', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ phone: this.pairingPhone })
                        });
                        const data = await res.json();
                        if (res.ok && data.success) {
                            this.pairingCodeResult = data.code;
                            this.showToast('Kode pairing berhasil diminta!');
                        } else {
                            this.showToast(data.error || 'Gagal meminta kode pairing', 'error');
                        }
                    } catch (e) {
                        this.showToast('Error koneksi', 'error');
                    } finally {
                        this.pairLoading = false;
                    }
                },

                async restartSocket() {
                    if (!confirm('Restart koneksi WhatsApp?')) return;
                    try {
                        const res = await fetch('/api/dashboard/socket/restart', {
                            method: 'POST',
                        });
                        if (!res.ok) throw new Error('restart failed');
                        this.showToast('Koneksi sedang di-restart...');
                        setTimeout(() => this.fetchStatus(), 2000);
                    } catch (e) {}
                },

                async logoutSocket() {
                    if (!confirm('Putuskan sesi WhatsApp dari perangkat ini?')) return;
                    try {
                        const res = await fetch('/api/dashboard/socket/logout', {
                            method: 'POST',
                        });
                        if (!res.ok) throw new Error('logout failed');
                        this.showToast('Sesi WhatsApp telah dihapus.');
                        setTimeout(() => this.fetchStatus(), 2000);
                    } catch (e) {}
                },

                async sendSimMessage() {
                    const text = this.simInput.trim();
                    if (!text) return;

                    this.simChatHistory.push({ id: Date.now(), role: 'user', text: text });
                    this.simInput = '';
                    this.simLoading = true;

                    try {
                        const res = await fetch('/api/dashboard/ai-simulate', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ message: text })
                        });
                        const data = await res.json();
                        if (res.ok && data.reply) {
                            this.simChatHistory.push({ id: Date.now() + 1, role: 'assistant', text: data.reply });
                        } else {
                            this.simChatHistory.push({ id: Date.now() + 1, role: 'assistant', text: 'Error: ' + (data.error || 'Gagal memanggil model AI') });
                        }
                    } catch (e) {
                        this.simChatHistory.push({ id: Date.now() + 1, role: 'assistant', text: 'Gagal terhubung ke server simulasi.' });
                    } finally {
                        this.simLoading = false;
                    }
                },

                async sendTestMessage() {
                    this.testMsgLoading = true;
                    try {
                        let endpoint = '/api/otp/send';
                        let payload = { phoneNumber: this.testMsg.phone };
                        if (this.testMsg.type === 'custom') {
                            endpoint = '/api/dashboard/send';
                            payload = { target: this.testMsg.phone, message: this.testMsg.message };
                        }

                        const res = await fetch(endpoint, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload)
                        });
                        const data = await res.json();
                        if (res.ok && (data.success || data.status)) {
                            this.showToast('Pesan berhasil diproses!');
                            this.loadLogs();
                        } else {
                            this.showToast(data.error || 'Gagal mengirim pesan', 'error');
                        }
                    } catch (e) {
                        this.showToast('Gagal menghubungi server', 'error');
                    } finally {
                        this.testMsgLoading = false;
                    }
                },

                async loadLogs() {
                    try {
                        const res = await fetch('/api/dashboard/logs');
                        if (res.ok) {
                            const data = await res.json();
                            this.logs = data.logs || [];
                            this.stats = data.stats || {};
                        }
                    } catch (e) {}
                },

                async changePassword() {
                    if (this.passChange.new !== this.passChange.confirm) {
                        this.showToast('Konfirmasi kata sandi baru tidak cocok', 'error');
                        return;
                    }

                    try {
                        const res = await fetch('/api/dashboard/change-password', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                old_password: this.passChange.old,
                                new_password: this.passChange.new
                            })
                        });
                        const data = await res.json();
                        if (res.ok && data.success) {
                            this.showToast('Kata sandi berhasil diubah!');
                            this.passChange = { old: '', new: '', confirm: '' };
                            setTimeout(() => location.reload(), 1200);
                        } else {
                            this.showToast(data.error || 'Gagal mengubah kata sandi', 'error');
                        }
                    } catch (e) {
                        this.showToast('Error koneksi', 'error');
                    }
                },

                formatPhoneInput(e) {
                    let val = e.target.value;
                    let isPlus = val.startsWith('+');
                    let raw = val.replace(/\D/g, '');

                    if (isPlus) {
                        // +62 853 2344 1080
                        if (raw.startsWith('62')) {
                            let rest = raw.slice(2);
                            let parts = ['+62'];
                            if (rest.length > 0) parts.push(rest.slice(0, 3));
                            if (rest.length > 3) parts.push(rest.slice(3, 7));
                            if (rest.length > 7) parts.push(rest.slice(7, 11));
                            this.pairingPhone = parts.join(' ');
                        } else {
                            this.pairingPhone = '+' + raw;
                        }
                    } else {
                        // 0812 3456 7890
                        let parts = [];
                        if (raw.length > 0) parts.push(raw.slice(0, 4));
                        if (raw.length > 4) parts.push(raw.slice(4, 8));
                        if (raw.length > 8) parts.push(raw.slice(8, 12));
                        this.pairingPhone = parts.join(' ');
                    }
                },

                formatStatus(st) {
                    switch (st) {
                        case 'connected': return 'Terhubung';
                        case 'qr_ready': return 'Menunggu Pindai QR';
                        case 'connecting': return 'Menghubungkan...';
                        default: return 'Terputus';
                    }
                },

                formatTime(iso) {
                    if (!iso) return '-';
                    const d = new Date(iso);
                    return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                }
            };
        }
