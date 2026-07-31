<!-- Widget Chatbot flottant -->
<div id="chatbotBubble" class="chatbot-bubble">
    <i class="fa-solid fa-robot"></i>
</div>

<div id="chatbotWindow" class="chatbot-window hidden">
    <div class="chatbot-header">
        <button type="button" id="toggleSidebar" class="chatbot-icon-btn">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="chatbot-header-avatar">
            <i class="fa-solid fa-robot"></i>
        </div>
        <div class="chatbot-header-text">
            <span class="chatbot-title">Assistant PalmFox</span>
            <span class="chatbot-subtitle">Répond généralement instantanément</span>
        </div>
        <!-- BOUTON STATISTIQUES -->
        <button type="button" id="openStatsBtn" class="chatbot-icon-btn stats-btn" title="Mes statistiques">
            <i class="fa-solid fa-chart-pie"></i>
        </button>
        <button type="button" id="newConversationBtn" class="chatbot-icon-btn">
            <i class="fa-solid fa-plus"></i>
        </button>
        <button type="button" id="closeChatbotWidget">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <div id="chatbotSidebar" class="chatbot-sidebar hidden">
        <div class="chatbot-sidebar-title">Conversations</div>
        <div id="conversationsList" class="conversations-list"></div>
    </div>

    <div class="chatbot-messages" id="chatbotMessages"></div>

    <div class="chatbot-input-area">
        <input type="text" id="chatbotInput" placeholder="Posez une question...">
        <button type="button" id="chatbotSend">
            <i class="fa-solid fa-paper-plane"></i>
        </button>
    </div>
</div>

<!-- OVERLAY TABLEAU DE BORD STATISTIQUES -->
<div id="statsOverlay" class="stats-overlay">
    <div class="stats-modal">
        <div class="stats-modal-head">
            <div class="stats-modal-icon"><i class="fa-solid fa-chart-pie"></i></div>
            <div>
                <h3>Mes statistiques</h3>
                <p>Activité des 30 derniers jours</p>
            </div>
            <button type="button" id="closeStatsModal" class="stats-modal-close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="stats-modal-body" id="statsModalBody">
            <div class="stats-empty">Chargement des statistiques…</div>
        </div>
    </div>
</div>
