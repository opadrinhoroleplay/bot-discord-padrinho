const { Message } = require('discord.js');

class BadWords {
    static badWords = [
        // Add your list of bad words here
        // This is just an example, you should replace with your actual list
        'badword1',
        'badword2',
        'badword3'
    ];

    /**
     * Scan a message for bad words
     * @param {Message} message 
     * @returns {boolean}
     */
    static scan(message) {
        const content = message.content.toLowerCase();
        
        // Check if any bad word is in the message
        const foundBadWord = this.badWords.some(word => content.includes(word.toLowerCase()));

        if (foundBadWord) {
            message.delete().catch(console.error);
            return true;
        }

        return false;
    }

    /**
     * Add a word to the bad words list
     * @param {string} word 
     */
    static addWord(word) {
        if (!this.badWords.includes(word.toLowerCase()))
            this.badWords.push(word.toLowerCase());
    }

    /**
     * Remove a word from the bad words list
     * @param {string} word 
     */
    static removeWord(word) {
        const index = this.badWords.indexOf(word.toLowerCase());
        if (index > -1) this.badWords.splice(index, 1);
    }
}

module.exports = BadWords; 