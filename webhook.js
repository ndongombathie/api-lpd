const http = require('http');
const { exec } = require('child_process');

http.createServer((req, res) => {
    if (req.method === 'POST') {
        console.log('Webhook reçu pour API');

        exec('bash deploy.sh', { cwd: __dirname }, (err, stdout, stderr) => {
            console.log(stdout);
            console.error(stderr);
        });

        res.end('Déploiement API lancé');
    } else {
        res.end('OK');
    }
}).listen(9001);

console.log("Webhook API lancé sur port 9001");
