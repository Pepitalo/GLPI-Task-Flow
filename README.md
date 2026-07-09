# GLPI-Task-Flow
GLPI Plugin that allows the creation of a list of task to be sent depending on the answers of a form in a ticket. Allows for parametrized and customisable responses 
Please note that this current version only supports answers from checkboxes, this will be improved soon. This plugin also relies on the presence of FormsCreator.

 ## How to use
 To set up a parametrized response, take the question ID from formscreator which can be found in your form's target ticket, name your parametrized response to that question and click "Ajouter". Then add in a response for each answer you want.

 ## Requirements
 This plugin requires Formscreator to be installed as a plugin, therefore will probably not be compatible with GLPI 11.x.x and up without prior modification.

 ## How it functions
 This plugin attaches to the end of the creation of a ticket by FormsCreator, reads the question IDs sent, and if it matches one or multiple IDs in its database will atempt to read all of the answers from these questions and send tasks one by one for any that have been programmed. Its database is a sub table which's name can be found in the code, therefore this plugin does not affect the rest of the SQL database.
