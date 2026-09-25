import JSZip from 'jszip';

try {
    console.log('[web.whatsapp.content.accesible.js] - CONTENT ACCESSIBLE!');

    // Tope de mensajes a exportar por conversación
    const MAX_MESSAGES_PER_CHAT = 200;
    const MAX_HISTORY_LOADS_PER_CHAT = 10;
    const CHAT_HISTORY_TIMEOUT_MS = 20000;
    const PHONE_NUMBER_TIMEOUT_MS = 5000;

    const withTimeout = async (promise, timeoutMs) => {
        let timeoutId;
        const timeout = new Promise((resolve, reject) => {
            timeoutId = setTimeout(() => {
                reject(new Error('WhatsApp no respondió dentro del tiempo límite'));
            }, timeoutMs);
        });

        try {
            // WhatsApp no permite cancelar estas consultas; dejamos de esperarlas al vencer el plazo.
            return await Promise.race([promise, timeout]);
        } finally {
            clearTimeout(timeoutId);
        }
    };

    // Acceso directo a los módulos internos de WhatsApp Web (mismos que usa whatsapp-web.js).
    // Se resuelven recién al momento de exportar, cuando la app ya está cargada.
    const requireModule = (moduleName) => {
        if (typeof window.require !== 'function') {
            throw new Error('WhatsApp Web todavía no terminó de cargar (window.require no disponible)');
        }
        const module = window.require(moduleName);
        if (!module) {
            throw new Error(`Módulo interno de WhatsApp no encontrado: ${moduleName}`);
        }
        return module;
    };

    // Resuelve el número de teléfono real de un chat @lid.
    // Misma lógica que WWebJS.enforceLidAndPnRetrieval de whatsapp-web.js.
    const resolveLidPhoneNumber = async (wid) => {
        const { getPhoneNumber } = requireModule('WAWebApiContact');

        let phoneWid = getPhoneNumber(wid);
        if (!phoneWid) {
            const { queryWidExists } = requireModule('WAWebQueryExistsJob');
            await withTimeout(queryWidExists(wid), PHONE_NUMBER_TIMEOUT_MS);
            phoneWid = getPhoneNumber(wid);
        }
        return phoneWid?.user;
    };


    // Función para convertir timestamp Unix a fecha legible
    const formatTimestamp = (timestamp) => {
        const date = new Date(timestamp * 1000);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day} ${hours}:${minutes}`;
    };

    // Tipos de mensaje de sistema que no aportan nada a la conversación
    const SYSTEM_MESSAGE_TYPES = [
        'notification',
        'notification_template',
        'e2e_notification',
        'broadcast_notification',
        'newsletter_notification',
        'gp2',
        'ciphertext',
        'protocol',
    ];

    // Placeholders para mensajes multimedia / especiales
    const MEDIA_PLACEHOLDERS = {
        image: '<imagen enviada>',
        video: '<video enviado>',
        ptt: '<nota de voz enviada>',
        audio: '<audio enviado>',
        document: '<documento enviado>',
        sticker: '<sticker enviado>',
        location: '<ubicación compartida>',
        vcard: '<contacto compartido>',
        multi_vcard: '<contactos compartidos>',
        revoked: '<mensaje eliminado>',
        call_log: '<llamada>',
    };

    // Tipos cuyo texto viene en body: texto plano, templates con botones y
    // las respuestas al tocar un botón/opción de lista
    const BODY_MESSAGE_TYPES = ['chat', 'template', 'buttons_response', 'list_response', 'template_button_reply'];

    // Labels de botones interactivos del mensaje (bots / API de WhatsApp Business)
    const getButtonLabels = (msg) => {
        if (Array.isArray(msg.dynamicReplyButtons) && msg.dynamicReplyButtons.length) {
            return msg.dynamicReplyButtons
                .map(b => b?.buttonText?.displayText)
                .filter(Boolean);
        }
        if (Array.isArray(msg.hydratedButtons) && msg.hydratedButtons.length) {
            return msg.hydratedButtons
                .map(b => b?.quickReplyButton?.displayText
                    || b?.urlButton?.displayText
                    || b?.callButton?.displayText
                    || b?.hydratedButton?.quickReplyButton?.displayText)
                .filter(Boolean);
        }
        return [];
    };

    // Devuelve el texto a exportar para un mensaje, o null si hay que omitirlo
    const buildMessageText = (msg) => {
        if (SYSTEM_MESSAGE_TYPES.includes(msg.type)) {
            return null;
        }

        let text;
        if (BODY_MESSAGE_TYPES.includes(msg.type)) {
            text = (msg.body || '').trim();
            if (!text.length) {
                return null;
            }
        } else {
            let placeholder = MEDIA_PLACEHOLDERS[msg.type] ?? `<${msg.type}>`;
            if (msg.type === 'document' && msg.filename) {
                placeholder = `<documento enviado: ${msg.filename}>`;
            }

            // En mensajes multimedia el texto viene en caption (body puede traer el thumbnail)
            const caption = (msg.caption || '').trim();
            text = caption.length ? `${placeholder} ${caption}` : placeholder;
        }

        const buttons = getButtonLabels(msg);
        if (buttons.length) {
            text += `\n[Botones: ${buttons.join(' | ')}]`;
        }
        return text;
    };

    // Carga hacia atrás con límites de mensajes, intentos y tiempo total por chat.
    const loadChatMessages = async (chat) => {
        let msgs = chat.msgs.getModelsArray();
        const deadline = performance.now() + CHAT_HISTORY_TIMEOUT_MS;

        for (let attempt = 0; attempt < MAX_HISTORY_LOADS_PER_CHAT; attempt++) {
            if (msgs.length >= MAX_MESSAGES_PER_CHAT) {
                break;
            }

            const remainingMs = deadline - performance.now();
            if (remainingMs <= 0) {
                console.warn('[web.whatsapp.content.accesible.js] - Tiempo de carga agotado; se exportan los mensajes disponibles', chat.id._serialized);
                break;
            }

            const previousCount = msgs.length;
            try {
                const { loadEarlierMsgs } = requireModule('WAWebChatLoadMessages');
                const earlier = await withTimeout(loadEarlierMsgs({ chat }), remainingMs);
                msgs = chat.msgs.getModelsArray();

                const hasNoEarlierMessages = !earlier || earlier.length === 0;
                if (hasNoEarlierMessages) {
                    break;
                }

                const hasNoNewMessages = msgs.length <= previousCount;
                if (hasNoNewMessages) {
                    console.warn('[web.whatsapp.content.accesible.js] - La carga no agregó mensajes; se continúa con el siguiente chat', chat.id._serialized);
                    break;
                }
            } catch (loadError) {
                msgs = chat.msgs.getModelsArray();
                console.warn('[web.whatsapp.content.accesible.js] - Historial incompleto; se exportan los mensajes disponibles', chat.id._serialized, loadError);
                break;
            }

            const reachedLoadLimit = attempt + 1 === MAX_HISTORY_LOADS_PER_CHAT;
            const needsMoreMessages = msgs.length < MAX_MESSAGES_PER_CHAT;
            if (reachedLoadLimit && needsMoreMessages) {
                console.warn('[web.whatsapp.content.accesible.js] - Límite de cargas alcanzado; se exportan los mensajes disponibles', chat.id._serialized);
            }
        }

        return msgs
            .slice()
            .sort((a, b) => a.t - b.t)
            .slice(-MAX_MESSAGES_PER_CHAT);
    };

    const getContactName = (chat, phoneNumber) => {
        const contact = chat.contact;
        return contact?.name || contact?.pushname || contact?.verifiedName || phoneNumber;
    };

    // Genera el contenido del .txt de una conversación. Devuelve null si quedó vacía.
    const generateChatTxt = (chat, phoneNumber, msgs) => {
        const contactName = getContactName(chat, phoneNumber);

        const rendered = [];
        for (const msg of msgs) {
            const text = buildMessageText(msg);
            if (text === null) {
                continue;
            }
            const senderLabel = msg.id?.fromMe ? 'Yo' : contactName;
            rendered.push({ t: msg.t, line: `[${formatTimestamp(msg.t)}] ${senderLabel}: ${text}` });
        }

        if (!rendered.length) {
            return null;
        }

        const header = [
            `# Teléfono: ${phoneNumber}`,
            `# Contacto: ${contactName}`,
            `# Mensajes: ${rendered.length} | Desde: ${formatTimestamp(rendered[0].t)} | Hasta: ${formatTimestamp(rendered[rendered.length - 1].t)}`,
        ];

        return header.join('\n') + '\n\n' + rendered.map(r => r.line).join('\n') + '\n';
    };

    // Función para descargar un blob
    const downloadBlob = (blob, filename) => {
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);

        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        URL.revokeObjectURL(url);
    };


    // PUSHER CLIENTY GET CHAT MESSAGES
    window.addEventListener('getSeedChats', async function () {
        console.log('[web.whatsapp.content.accesible.js] - getSeedChats');

        // Helper para emitir progreso
        const emitProgress = (current, total, phase) => {
            window.dispatchEvent(new CustomEvent('exportProgress', {
                detail: { current, total, phase }
            }));
        };

        try {
            console.log('[web.whatsapp.content.accesible.js] - WWeb version', window.Debug?.VERSION);

            // Fase 1: Obteniendo chats
            emitProgress(0, 0, 'loading_chats');

            const ChatCollection = requireModule('WAWebCollections').Chat;

            // c.id.server: 'c.us' = contacto, 'lid' = contacto con id anónimo,
            // 'g.us' = grupo, 'newsletter' = canal, 'broadcast' = estados.
            // c.t = timestamp del último mensaje del chat (el que ordena el sidebar).
            const seedChats = ChatCollection.getModelsArray()
                .filter(chat => ['c.us', 'lid'].includes(chat?.id?.server))
                .filter(chat => chat?.id?.user)
                .filter(chat => chat.t)
                .filter(chat => chat.id.user.length > 6)
            ;
            console.log('[web.whatsapp.content.accesible.js] - seedChats', seedChats);

            // Fase 2: Exportando conversaciones
            const total = seedChats.length;
            const zip = new JSZip();
            const usedFileNames = new Set();
            let exportedCount = 0;
            emitProgress(0, total, 'exporting_chats');

            for (let i = 0; i < seedChats.length; i++) {
                const seedChat = seedChats[i];
                let phoneNumber = seedChat.id.user;
                console.log(`[web.whatsapp.content.accesible.js] - Iniciando ${i + 1}/${total}: ${seedChat.id._serialized}`);

                try {
                    if (seedChat.id.server === 'lid') {
                        try {
                            phoneNumber = await resolveLidPhoneNumber(seedChat.id) ?? phoneNumber;
                        } catch (lidError) {
                            console.warn('[web.whatsapp.content.accesible.js] - No se pudo resolver @lid', seedChat.id._serialized, lidError);
                        }
                    }

                    const msgs = await loadChatMessages(seedChat);
                    const txtContent = generateChatTxt(seedChat, phoneNumber, msgs);

                    // Solo exportar conversaciones con mensajes
                    if (txtContent !== null) {
                        let fileName = `${phoneNumber}.txt`;
                        let suffix = 2;
                        while (usedFileNames.has(fileName)) {
                            fileName = `${phoneNumber}-${suffix++}.txt`;
                        }
                        usedFileNames.add(fileName);
                        zip.file(fileName, txtContent);
                        exportedCount++;
                    }
                } catch (chatError) {
                    console.warn('[web.whatsapp.content.accesible.js] - Error exportando chat', seedChat.id._serialized, chatError);
                }

                emitProgress(i + 1, total, 'exporting_chats');
                console.log(`[web.whatsapp.content.accesible.js] - Procesado ${i + 1}/${total}: ${phoneNumber}`);
            }

            // Fase 3: Generando ZIP
            emitProgress(total, total, 'generating_zip');

            const zipBlob = await zip.generateAsync({ type: 'blob', compression: 'DEFLATE' });
            downloadBlob(zipBlob, `whatsapp-conversaciones-${Date.now()}.zip`);

            // Notificar éxito
            window.dispatchEvent(new CustomEvent('getSeedChatsComplete', { detail: { success: true, count: exportedCount } }));
            console.log('[web.whatsapp.content.accesible.js] - ZIP generated and downloaded', exportedCount);
        } catch (err) {
            console.error('[web.whatsapp.content.accesible.js] - CLIENTY GET SEED CHATS ERROR', err);
            window.dispatchEvent(new CustomEvent('getSeedChatsComplete', { detail: { success: false, error: err.message } }));
        }
    });

} catch (injectedScriptError) {
    console.log({ injectedScriptError });
}
