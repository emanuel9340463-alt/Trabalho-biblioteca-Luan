document.getElementById("alunoForm").addEventListener("submit", function (e) {
    e.preventDefault(); // Impede o recarregamento da página

    let nome = document.getElementById("nome").value;
    let nascimento = document.getElementById("nascimento").value;
    let turma = document.getElementById("turma").value;
    let mensagemDiv = document.getElementById("mensagem");

    // Limpa mensagens anteriores
    mensagemDiv.innerHTML = "";

    // Prepara os dados para envio
    let dados = new URLSearchParams();
    dados.append("nome", nome);
    dados.append("turma", turma);
    dados.append("data_nascimento", nascimento);

    fetch("cadastroaluno.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: dados
    })
    .then(response => response.text())
    .then(data => {
        let resposta = data.trim();

        if (resposta === "sucesso") {
            mensagemDiv.innerHTML = `
                <div class="alert alert-success mt-3" role="alert">
                    Aluno cadastrado com sucesso!
                </div>
            `;
            // Limpa o formulário após o sucesso
            document.getElementById("alunoForm").reset();
        } else {
            mensagemDiv.innerHTML = `
                <div class="alert alert-danger mt-3" role="alert">
                    ${resposta}
                </div>
            `;
        }
    })
    .catch(error => {
        mensagemDiv.innerHTML = `
            <div class="alert alert-danger mt-3" role="alert">
                Erro ao conectar ao servidor.
            </div>
        `;
    });
});