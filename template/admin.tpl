<div class="titrePage">
  <h2>Gemini Image Captioner</h2>
</div>

<div class="adminInfoPanel">
  <h3>About this Plugin</h3>
  <p>This plugin uses Google's Gemini Pro Vision model to automatically generate captions for your images.</p>
  
  <h3>How to Use</h3>
  <ol>
    <li>Configure the plugin settings below</li>
    <li>Go to the Batch Manager in Piwigo admin</li>
    <li>Select the images you want to caption</li>
    <li>Click the "Caption with ChatGPT" button</li>
    <li>The plugin will process each image and add AI-generated captions to the descriptions</li>
  </ol>
</div>

<form method="post" action="" class="properties">
  <fieldset>
    <legend>Configuration</legend>
    
    <div class="adminInfoPanel">
      <h4>Google Gemini API Settings</h4>
      <p>
        <label for="api_key">API Key:</label>
        <input type="text" name="api_key" id="api_key" value="{$API_KEY}" size="50">
      </p>
      <p>
        <label for="model">Model:</label>
        <select name="model" id="model">
          <option value="gemini-2.0-flash" {if $MODEL == 'gemini-2.0-flash'}selected{/if}>Gemini 2.0 flash</option>
          <option value="gemini-2.0-pro" {if $MODEL == 'gemini-2.0-pro'}selected{/if}>Gemini 2.0 pro</option>
        </select>
      </p>
    </div>
    
    <div class="adminInfoPanel">
      <h4>Caption Generation Settings</h4>
      <p>
        <label for="user_prompt">User Prompt:</label>
        <textarea name="user_prompt" id="user_prompt" rows="3" cols="80">{$USER_PROMPT}</textarea>
        <small>Instructions for how the AI should generate the caption.</small>
      </p>
    </div>
        
    <p class="formButtons">
      <input type="submit" name="submit" value="Save Settings" class="buttonLike">
      <a href="admin.php?page=batch_manager" class="buttonLike">Go to Batch Manager</a>
    </p>
  </fieldset>
</form>
<div class="adminInfoPanel">
  <h3>Version</h3>
  <p>Version: {$PLUGIN_VERSION}</p>
</div>
