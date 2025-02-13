const words = {
    adjectives: ['grande', 'pequeno', 'forte', 'fraco', 'rapido', 'lento', 'alto', 'baixo'],
    nouns: ['carro', 'casa', 'rua', 'cidade', 'pessoa', 'animal', 'comida', 'agua'],
    verbs: ['correr', 'andar', 'saltar', 'nadar', 'voar', 'comer', 'beber', 'dormir']
};

class WordUtils {
    /**
     * Generate a slug from a string
     * @param {string} str 
     * @returns {string}
     */
    static slugify(str) {
        return str
            .toLowerCase()
            .trim()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/[\s-]+/g, '-');
    }

    /**
     * Generate a random word from a category
     * @param {string} category 
     * @returns {string}
     */
    static getRandomWord(category) {
        const wordList = words[category];
        return wordList[Math.floor(Math.random() * wordList.length)];
    }

    /**
     * Generate three random words
     * @returns {string}
     */
    static generateWhatThreeWords() {
        const adjective = this.getRandomWord('adjectives');
        const noun = this.getRandomWord('nouns');
        const verb = this.getRandomWord('verbs');
        return `${adjective}-${noun}-${verb}`;
    }

    /**
     * Get a random insult
     * @returns {string}
     */
    static getInsult() {
        const insults = ['cabrão', 'idiota', 'burro', 'palhaço', 'otário', 'imbecil', 'energúmeno', 'palerma'];
        return insults[Math.floor(Math.random() * insults.length)];
    }
}

module.exports = WordUtils; 