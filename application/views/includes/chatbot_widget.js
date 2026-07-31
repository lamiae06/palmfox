document.addEventListener("DOMContentLoaded", function () {

    const bubble = document.getElementById("chatbotBubble");
    const win = document.getElementById("chatbotWindow");
    const closeBtn = document.getElementById("closeChatbotWidget");
    const input = document.getElementById("chatbotInput");
    const sendBtn = document.getElementById("chatbotSend");
    const messages = document.getElementById("chatbotMessages");
    const sidebar = document.getElementById("chatbotSidebar");
    const toggleSidebarBtn = document.getElementById("toggleSidebar");
    const newConversationBtn = document.getElementById("newConversationBtn");
    const conversationsList = document.getElementById("conversationsList");

    const API_BASE = "../../../api";

    // conversation active en cours (persistée dans sessionStorage pour survivre à la navigation entre pages)
    let currentConversationId = parseInt(sessionStorage.getItem("palmfox_current_conversation") || "0", 10);

    function setCurrentConversation(id) {
        currentConversationId = id;
        sessionStorage.setItem("palmfox_current_conversation", id);
    }

    // ---------- Affichage des messages ----------

    function creerBoutonEdit(row) {
        const editBtn = document.createElement("button");
        editBtn.type = "button";
        editBtn.className = "chatbot-edit-btn";
        editBtn.innerHTML = '<i class="fa-solid fa-pen"></i>';
        editBtn.addEventListener("click", () => startEdit(row));
        return editBtn;
    }

    function addMessage(text, type, messageId) {
        const row = document.createElement("div");
        row.className = "chatbot-row " + type;
        if (messageId) row.dataset.messageId = messageId;

        if (type === "bot") {
            const avatar = document.createElement("div");
            avatar.className = "chatbot-avatar";
            avatar.innerHTML = '<i class="fa-solid fa-robot"></i>';
            row.appendChild(avatar);
        }

        const msg = document.createElement("div");
        msg.className = "chatbot-msg " + type;
        msg.textContent = text;
        row.appendChild(msg);

        if (type === "user") {
            row.appendChild(creerBoutonEdit(row));
        }

        messages.appendChild(row);
        messages.scrollTop = messages.scrollHeight;

        return row;
    }

    function afficherMessageAccueil() {
        messages.innerHTML = "";
        const row = document.createElement("div");
        row.className = "chatbot-row bot";
        row.innerHTML = `
            <div class="chatbot-avatar"><i class="fa-solid fa-robot"></i></div>
            <div class="chatbot-msg bot">Bonjour ! Je suis l'assistant IA de PalmFox. Comment puis-je vous aider ?</div>
        `;
        messages.appendChild(row);
    }

    // ---------- Chargement d'une conversation depuis la base ----------

    async function chargerConversation(id) {
        setCurrentConversation(id);
        messages.innerHTML = "";

        try {
            const res = await fetch(`${API_BASE}/conversation_messages.php?id=${id}`);
            const data = await res.json();

            if (data.error || !data.messages) {
                afficherMessageAccueil();
                return;
            }

            data.messages.forEach(m => {
                const type = m.role === "assistant" ? "bot" : "user";
                addMessage(m.contenu, type, m.id_message);
            });

            messages.scrollTop = messages.scrollHeight;
        } catch (err) {
            console.error("Erreur chargement conversation :", err);
            afficherMessageAccueil();
        }
    }

    // ---------- Liste des conversations (sidebar) ----------

    async function chargerListeConversations() {
        try {
            const res = await fetch(`${API_BASE}/conversations_list.php`);
            const data = await res.json();

            conversationsList.innerHTML = "";

            if (!data.conversations || data.conversations.length === 0) {
                conversationsList.innerHTML = '<div class="conversation-item" style="cursor:default;color:#999;">Aucune conversation</div>';
                return;
            }

            data.conversations.forEach(conv => {
                const item = document.createElement("div");
                item.className = "conversation-item";
                if (conv.id_conversation === currentConversationId) {
                    item.classList.add("active");
                }

                const textDiv = document.createElement("div");
                textDiv.className = "conversation-item-text";
                textDiv.innerHTML = `
                    ${conv.titre || "Conversation sans titre"}
                    <span class="conv-date">${formatDate(conv.date_creation)}</span>
                `;
                textDiv.addEventListener("click", () => {
                    chargerConversation(conv.id_conversation);
                    sidebar.classList.add("hidden");
                });

                const deleteBtn = document.createElement("button");
                deleteBtn.type = "button";
                deleteBtn.className = "conversation-delete-btn";
                deleteBtn.innerHTML = '<i class="fa-solid fa-trash"></i>';
                deleteBtn.addEventListener("click", (e) => {
                    e.stopPropagation();
                    supprimerConversation(conv.id_conversation);
                });

                item.appendChild(textDiv);
                item.appendChild(deleteBtn);
                conversationsList.appendChild(item);
            });
        } catch (err) {
            console.error("Erreur chargement liste conversations :", err);
        }
    }

    async function supprimerConversation(id) {
        if (!confirm("Supprimer cette conversation ?")) return;

        try {
            await fetch(`${API_BASE}/conversation_delete.php`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ conversation_id: id })
            });

            // Si c'était la conversation actuellement ouverte, on revient à l'accueil
            if (id === currentConversationId) {
                setCurrentConversation(0);
                afficherMessageAccueil();
            }

            chargerListeConversations(); // rafraîchit la liste
        } catch (err) {
            console.error("Erreur suppression conversation :", err);
        }
    }

    function formatDate(dateStr) {
        const d = new Date(dateStr.replace(" ", "T"));
        return d.toLocaleDateString("fr-FR", { day: "2-digit", month: "2-digit", year: "numeric" }) +
               " " + d.toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" });
    }

    toggleSidebarBtn.addEventListener("click", () => {
        sidebar.classList.toggle("hidden");
        if (!sidebar.classList.contains("hidden")) {
            chargerListeConversations();
        }
    });

    newConversationBtn.addEventListener("click", () => {
        setCurrentConversation(0);
        afficherMessageAccueil();
        sidebar.classList.add("hidden");
    });

    // ---------- Ouverture / fermeture du widget ----------

    if (sessionStorage.getItem("palmfox_chat_open") === "true") {
        win.classList.remove("hidden");
    }

    bubble.addEventListener("click", () => {
        win.classList.toggle("hidden");
        sessionStorage.setItem("palmfox_chat_open", !win.classList.contains("hidden"));

        // Au premier affichage : charge la conversation en cours ou l'accueil
        if (!win.classList.contains("hidden")) {
            if (currentConversationId > 0) {
                chargerConversation(currentConversationId);
            } else {
                afficherMessageAccueil();
            }
        }
    });

    closeBtn.addEventListener("click", () => {
        win.classList.add("hidden");
        sessionStorage.setItem("palmfox_chat_open", "false");
    });

    // ---------- Édition d'un message utilisateur ----------

    function startEdit(row) {
        const msgDiv = row.querySelector(".chatbot-msg");
        const originalText = msgDiv.textContent;

        row.innerHTML = `
            <div class="chatbot-edit-box">
                <input type="text" class="chatbot-edit-input" value="${originalText.replace(/"/g, '&quot;')}">
                <div class="chatbot-edit-actions">
                    <button type="button" class="chatbot-edit-save"><i class="fa-solid fa-check"></i></button>
                    <button type="button" class="chatbot-edit-cancel"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
        `;

        const editInput = row.querySelector(".chatbot-edit-input");
        editInput.focus();
        editInput.setSelectionRange(editInput.value.length, editInput.value.length);

        row.querySelector(".chatbot-edit-save").addEventListener("click", () => {
            const newText = editInput.value.trim();
            if (!newText) return;
            confirmEdit(row, newText);
        });

        row.querySelector(".chatbot-edit-cancel").addEventListener("click", () => {
            chargerConversation(currentConversationId); // annule → recharge l'état sauvegardé
        });

        editInput.addEventListener("keydown", (e) => {
            if (e.key === "Enter") {
                const newText = editInput.value.trim();
                if (newText) confirmEdit(row, newText);
            }
        });
    }

    async function confirmEdit(row, newText) {
        const messageId = row.dataset.messageId;

        try {
            await fetch(`${API_BASE}/message_edit.php`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ message_id: messageId })
            });
        } catch (err) {
            console.error("Erreur édition :", err);
        }

        // Supprimer ce message + tous ceux qui suivent dans le DOM
        let next = row;
        while (next) {
            const toRemove = next;
            next = next.nextElementSibling;
            toRemove.remove();
        }

        // Renvoyer le message modifié
        sendMessage(newText);
    }

    // ---------- Envoi d'un message ----------

    function sendMessage(editedText) {
        const text = editedText !== undefined ? editedText : input.value.trim();
        if (!text) return;

        addMessage(text, "user");
        if (editedText === undefined) input.value = "";

        const loading = document.createElement("div");
        loading.className = "chatbot-msg bot";
        loading.id = "loadingMessage";
        loading.textContent = "⏳ Foxy IA réfléchit...";
        messages.appendChild(loading);
        messages.scrollTop = messages.scrollHeight;

        fetch(`${API_BASE}/chat.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ message: text, conversation_id: currentConversationId })
        })
        .then(res => {
            if (!res.ok) throw new Error("HTTP error " + res.status);
            return res.json();
        })
        .then(data => {
            const loader = document.getElementById("loadingMessage");
            if (loader) loader.remove();

            if (data.conversation_id) {
                setCurrentConversation(data.conversation_id);
            }

            addMessage(data.reply, "bot");
        })
        .catch(error => {
            console.error("Erreur détaillée :", error);
            const loader = document.getElementById("loadingMessage");
            if (loader) loader.remove();
            addMessage("Erreur de connexion avec le serveur PHP.", "bot");
        });
    }

    sendBtn.addEventListener("click", () => sendMessage());

    input.addEventListener("keydown", function(e){
        if(e.key === "Enter"){
            sendMessage();
        }
    });

    // ---------- Chargement initial ----------

    if (currentConversationId > 0) {
        chargerConversation(currentConversationId);
    } else {
        afficherMessageAccueil();
    }

});
