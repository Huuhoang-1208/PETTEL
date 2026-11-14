/** @param {import("./page-scope").PageScope} scope */
export default function initialize(scope) {
    // Chat button handler
    const chatBtn = document.getElementById('chat-btn');
    if (chatBtn) {
        scope.listen(chatBtn, 'click', () => {
            scope.navigate('/chat?id=1');
        });
    }
}
