document.getElementById("livroForm").addEventListener("submit", function(e){
    e.preventDefault();

    let titulo = document.getElementById("livro").value;
    let autor = document.getElementById("autor").value;
    let genero = document.getElementById("genero").value;
    let isbn = document.getElementById("isbn").value;
    let codigo_bibli = document.getElementById("codigo_bibli").value;

    fetch("", { // Envia para a própria página
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: `titulo=${encodeURIComponent(titulo)}&autor=${encodeURIComponent(autor)}&genero=${encodeURIComponent(genero)}&isbn=${encodeURIComponent(isbn)}&codigo_bibli=${encodeURIComponent(codigo_bibli)}`
    })
    .then(response => response.text())
    .then(data => {
        // Atualiza a página ou atualiza a mensagem
        location.reload(); 
    });
});