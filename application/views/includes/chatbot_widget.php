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
