const alunoForm = document.getElementById("alunoForm");
const mensagemAluno = document.getElementById("mensagem");
const botaoCadastroAluno = alunoForm.querySelector('button[type="submit"]');

alunoForm.addEventListener("submit", async (evento) => {
  evento.preventDefault();
  mensagemAluno.replaceChildren();
  botaoCadastroAluno.disabled = true;
  botaoCadastroAluno.textContent = "Cadastrando...";

  try {
    const resposta = await fetch("cadastroaluno.php", {
      method: "POST",
      body: new URLSearchParams(new FormData(alunoForm)),
    });
    const resultado = (await resposta.text()).trim();

    if (!resposta.ok || resultado !== "sucesso") {
      throw new Error(resultado || "Não foi possível cadastrar o aluno.");
    }

    alunoForm.reset();
    exibirMensagemAluno("Aluno cadastrado com sucesso.", "success");
  } catch (erro) {
    exibirMensagemAluno(
      erro.message === "Failed to fetch"
        ? "Erro ao conectar ao servidor. Tente novamente."
        : erro.message,
      "danger",
    );
  } finally {
    botaoCadastroAluno.disabled = false;
    botaoCadastroAluno.textContent = "Cadastrar aluno";
  }
});

function exibirMensagemAluno(texto, tipo) {
  const aviso = document.createElement("div");
  aviso.className = `alert alert-${tipo} mb-0`;
  aviso.setAttribute("role", tipo === "danger" ? "alert" : "status");
  aviso.textContent = texto;
  mensagemAluno.replaceChildren(aviso);
}
