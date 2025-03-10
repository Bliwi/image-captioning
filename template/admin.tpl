<div class="titrePage">
  <h2>ChatGPT Image Captioner</h2>
</div>

<div class="adminInfoPanel">
  <h3>About this Plugin</h3>
  <p>This plugin uses OpenAI's GPT-4o model to automatically generate captions for your images.</p>
  
  <h3>How to Use</h3>
  <ol>
    <li>Go to the Batch Manager in Piwigo admin</li>
    <li>Select the images you want to caption</li>
    <li>Click the "Caption with ChatGPT" button (added by JavaScript to the batch manager interface)</li>
    <li>The plugin will process each image and add AI-generated captions to the descriptions</li>
  </ol>
  
  <h3>Configuration</h3>
  <p>The plugin currently uses hardcoded values for API access. To change them, edit the main.inc.php file.</p>
  <ul>
    <li>API Key: Update the <code>$api_key</code> variable with your OpenAI API key</li>
    <li>Model: Currently using GPT-4o (<code>gpt-4o</code>)</li>
    <li>Prompt: The system and user prompts can be modified in the code</li>
  </ul>
  
  <p class="formButtons">
    <a href="admin.php?page=batch_manager" class="buttonLike">Go to Batch Manager</a>
  </p>
</div>

<div class="adminInfoPanel">
  <h3>Version</h3>
  <p>Version: {$PLUGIN_VERSION}</p>
</div>
