<script>
document.addEventListener('DOMContentLoaded', function() {
    // Find the batch manager action buttons container
    var actionButtons = document.querySelector('#action-buttons');
    
    if (actionButtons) {
        // Create our button
        var button = document.createElement('a');
        button.href = "{$CHATGPT_BUTTON}";
        button.className = "buttonLike";
        button.innerHTML = '<i class="icon-comment"></i> Caption with ChatGPT';
        
        // Add button to the container
        actionButtons.appendChild(button);
    }
});
</script>
