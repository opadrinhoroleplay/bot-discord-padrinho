const mysql = require('mysql2/promise');

module.exports = async (client) => {
    try {
        const connection = await mysql.createConnection({
            host: client.config.database.host,
            user: client.config.database.user,
            password: client.config.database.password,
            database: client.config.database.database,
            waitForConnections: true,
            connectionLimit: 10,
            queueLimit: 0
        });

        console.log('Connected to MySQL database');
        client.db = connection;
    } catch (error) {
        // Log the actual configuration being used (without password)
        console.error('Database config:', {
            host: client.config.database.host,
            user: client.config.database.user,
            database: client.config.database.database
        });
        console.error('Error connecting to database:', error);
        process.exit(1);
    }
}; 