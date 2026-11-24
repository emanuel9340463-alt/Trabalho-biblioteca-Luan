# Sistema de Gestão de Biblioteca

Sistema web para administrar o acervo de uma biblioteca escolar. Permite cadastrar livros e alunos, pesquisar títulos, registrar empréstimos e devoluções, promover alunos de turma e consultar o histórico.

## Funcionalidades

- Painel com totais de títulos, exemplares, alunos e empréstimos;
- Cadastro de livros com pesquisa automática de dados pelo ISBN;
- Leitura opcional de ISBN pelo leitor de câmera;
- Cadastro e pesquisa de alunos;
- Promoção de alunos para outra turma;
- Pesquisa de livros por título, autor, ISBN ou código;
- Registro de empréstimos com data prevista de devolução;
- Registro de devoluções e identificação de atrasos;
- Histórico por período e impressão de relatório.

## Tecnologias

- PHP
- MySQL/MariaDB
- HTML5 e CSS
- JavaScript
- Bootstrap 5
- ZXing para leitura de códigos de barras

## Pré-requisitos

- XAMPP ou ambiente equivalente;
- Apache;
- MySQL/MariaDB;
- PHP com os módulos MySQLi e cURL;
- Node.js e npm, para instalar o leitor de códigos;
- Navegador moderno.

O leitor de câmera funciona em `localhost` ou em uma conexão HTTPS. Em um celular,
`localhost` aponta para o próprio celular; ao acessar o XAMPP pelo endereço IP da
rede local, use HTTPS. Navegadores bloqueiam o acesso à câmera por HTTP nesse caso.

## Instalação no XAMPP

1. Coloque a pasta do projeto em `C:\xampp\htdocs\`.
2. Na pasta do projeto, execute `npm ci` para instalar as dependências JavaScript,
   incluindo o leitor de códigos usado pela câmera.
3. Inicie **Apache** e **MySQL** pelo painel do XAMPP.
4. Acesse `http://localhost/phpmyadmin`.
5. Importe o arquivo `database/banco.sql`.
6. Confirme que o banco `biblioteca_luan` foi criado.
7. Crie o primeiro usuário administrativo conforme **Configuração de acesso**.
8. Abra `http://localhost/Trabalho-biblioteca-Luan/login.php`.

O banco já vem configurado no projeto com o usuário padrão do XAMPP:

```php
$host = "localhost";
$usuario = "root";
$senha = "";
$banco = "biblioteca_luan";
```

Se o MySQL tiver outra senha ou usuário, altere os valores em `config/conexao.php`.

## Configuração de acesso

Todas as páginas de gestão e APIs internas exigem uma sessão autenticada. Não existe cadastro público de administradores. Para criar o primeiro usuário, gere um hash localmente em um PowerShell (a senha é lida sem exibi-la):

```powershell
$senhaSegura = Read-Host "Senha do administrador" -AsSecureString
$credencial = New-Object System.Management.Automation.PSCredential("admin", $senhaSegura)
$senha = $credencial.GetNetworkCredential().Password
$hash = $senha | & C:\xampp\php\php.exe C:\xampp\htdocs\Trabalho-biblioteca-Luan\database\gerar_hash_senha.php
Remove-Variable senha, senhaSegura, credencial
$hash
```

Digite a senha no terminal e copie somente o hash gerado. No phpMyAdmin, execute a consulta abaixo substituindo o e-mail e o hash:

```sql
INSERT INTO biblioteca_luan.usuarios (email, senha)
VALUES ('admin@exemplo.local', '$argon2id$v=19$m=65536,t=4,p=1$d0FDdGNIQUYxZEloRm1pbA$C+AYYwvTWI9yLViCZwLA44U2OOpU/P/1kpKfrCzX4Mc');
```

Não salve a senha em texto puro nem publique o hash. Para adicionar outros administradores, repita o procedimento com um e-mail distinto.

Para conferir localmente se uma senha corresponde ao hash atualmente salvo pela aplicação, sem enviar a senha ao navegador ou registrá-la em logs, use o PowerShell:

```powershell
$senhaSegura = Read-Host "Senha a conferir" -AsSecureString
$credencial = New-Object System.Management.Automation.PSCredential("admin", $senhaSegura)
$senha = $credencial.GetNetworkCredential().Password
$senha | & C:\xampp\php\php.exe C:\xampp\htdocs\Trabalho-biblioteca-Luan\database\verificar_senha_admin.php admin@gmail.com
Remove-Variable senha, senhaSegura, credencial
```

O verificador é somente CLI, consulta o banco configurado em `config/conexao.php` e informa apenas se a senha corresponde. Nunca cole a senha no chat.

### Atualização de instalações existentes

O login aceita somente senhas com hash. Se a tabela `usuarios` ainda tiver senhas em texto puro, faça backup do banco, atualize o projeto e execute o SQL atualizado (ele cria a tabela de limitação de tentativas sem apagar as demais tabelas ou registros). Antes de liberar o acesso à aplicação atualizada, execute no PowerShell:

```powershell
& C:\xampp\php\php.exe C:\xampp\htdocs\Trabalho-biblioteca-Luan\database\migrar_senhas_legadas.php
```

O utilitário acessa o banco pela configuração existente, troca somente valores que não são hashes reconhecidos por hashes `password_hash()` e preserva os IDs, e-mails e as senhas que os usuários já conhecem. Usa Argon2id quando disponível no PHP local; caso contrário, usa `PASSWORD_DEFAULT`. É idempotente: executar novamente não re-hashará as senhas já migradas. Só pode ser executado por CLI, informa a quantidade migrada e nunca imprime nem registra senhas. Faça um backup antes: após a conversão, os valores originais não podem ser recuperados. Use a mesma versão de PHP configurada para a aplicação; usuários com senha antiga poderão entrar com a senha de sempre assim que a migração terminar.

Não mantenha uma cópia da tabela ou do arquivo exportado em local público. Se a migração falhar, ela faz rollback; corrija a conexão ou o esquema e execute novamente antes de liberar o login.

## Estrutura do projeto

```text
.
├── config/                 Conexão e funções de segurança compartilhadas
├── css/                    Folhas de estilo das páginas
├── database/               SQL e utilitários CLI para hashes e migração de senhas
├── img/                    Imagens e capas dos livros
├── includes/               Cabeçalho e rodapé compartilhados
├── js/                     Comportamentos JavaScript das páginas
├── php/
│   ├── actions/            Login, logout e operações de escrita
│   └── api/                Consultas em formato JSON
├── index.php               Painel principal
├── login.php               Entrada da área administrativa
├── cadastroaluno.php       Cadastro de alunos
├── cadastrolivro.php       Cadastro de livros
├── emprestimos.php         Empréstimos e devoluções
├── historico.php           Histórico por período
├── pesquisa.php            Pesquisa no acervo
├── pesquisar_alunos.php    Pesquisa de alunos
└── promover_alunos.php     Promoção de alunos entre turmas
```

## Banco de dados

As principais tabelas são:

- `alunos`: dados dos leitores e sua turma;
- `livros`: cadastro do acervo, quantidade e identificação;
- `emprestimos`: retiradas, prazo e data real de devolução;
- `usuarios`: usuários cadastrados no sistema.

Um empréstimo ativo é aquele cujo campo `data_devolucao_real` está vazio. A devolução é registrada preenchendo esse campo com a data atual.

O script `database/banco.sql` pode ser executado novamente: as tabelas já existentes são preservadas, as colunas de migração só são adicionadas quando faltam, e não há empréstimos de exemplo com IDs fixos. Faça backup do banco antes de executar qualquer atualização em uma instalação com dados importantes.

## Como usar

1. Cadastre pelo menos um livro e um aluno.
2. Em **Empréstimos**, pesquise e selecione o aluno e o livro.
3. Informe as datas de retirada e de devolução prevista.
4. Use o botão **Devolver** para concluir uma retirada ativa.
5. Consulte movimentações e atrasos em **Histórico** ou no painel inicial.

## Observações

- Códigos e dados são escapados antes de serem exibidos em HTML.
- Consultas com dados informados pelo usuário usam prepared statements.
- Empréstimos usam transação e bloqueio do livro para evitar disponibilidade incorreta.
- A data do empréstimo pode ser passada para registrar uma retirada real lançada com atraso, mas não pode ser futura. A devolução prevista deve ser igual ou posterior à data do empréstimo; pode ser passada para registrar um empréstimo histórico já vencido ou futura para um empréstimo corrente.
- Tentativas inválidas de login são contadas por hashes do e-mail normalizado e do endereço IP, nunca armazenando esses valores em claro. Da 5ª à 7ª falha há bloqueio de 30 segundos, da 8ª à 11ª de 2 minutos e, a partir da 12ª, de 15 minutos. Uma nova tentativa após uma hora reinicia o contador; registros sem atividade por 24 horas são removidos na próxima falha.
- Sessões usam cookies `HttpOnly`, `SameSite=Lax` e `Secure` quando o site está em HTTPS; as operações POST verificam tokens CSRF.
- Configure o Apache/PHP para manter `display_errors=Off` em produção. Os detalhes técnicos são enviados ao log de erros do servidor.
- A tabela `usuarios` não tem perfis: toda conta cadastrada recebe acesso administrativo. Cadastre somente pessoas autorizadas.
- Fora do XAMPP local, use HTTPS e um usuário MySQL próprio com senha e permissões mínimas, em vez de `root` sem senha.
- Os arquivos dentro de `node_modules/` são dependências locais gerenciadas pelo npm e não fazem parte da lógica da aplicação.
