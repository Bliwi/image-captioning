# Piwigo ChatGPT Caption Generator

A Piwigo plugin that generates captions for your images using the ChatGPT API. The goal of the plugin is to generate captions for use with piwigo's Quick search feature to find images more easily and accessibility.

# Gemini Branches are more up to date

I have spent more time working on the gemini branches because the API is cheaper, allowing me to test and develop the code more.

---

## Requirements

- A valid OpenAI API key.
https://platform.openai.com/docs/overview

---

## Installation
Clone this repo inside of your piwigo/plugins folder.

---

## Usage

1. **Set Up Your API Key**  
   Before using the plugin, you must configure your OpenAI API key in the plugin's settings page.

2. **Generate Captions**  
   - Navigate to the **Batch Manager**.
   - Select the images you want to caption.
   - Choose the "Caption with ChatGPT" option from the available actions.
   - Captions will be automatically generated and saved for the selected images.
  
#### Important
Using these API's will expose your private picutres to the api owners (Google, OpenAI), Do not caption images you are not comforatable with the idea of them being used for AI training by these companies.
I recommend only using the processing on images that are already available on the internet.

---

## Notes

- I'm not experienced with PHP and this plugin is still in early development, please open an issue for any bugs you may find.
- PR's and contributions to help maintain the plugin and implement new features are welcome.

---
## Todo's
 - [x] ~Batch manager integration.~
 - [ ] Overhaul the code to follow piwigo's plugin guidelines.
 - [ ] Server-sided captioning.
 - [ ] Single mode button.
 - [ ] Process new images automatically.
 - [ ] Translation support
---

## Acknowledgments

- [Piwigo](https://piwigo.org) for providing an amazing photo management platform.
- [OpenAI](https://openai.com) for the ChatGPT API.
