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
- Navegador moderno.

O leitor de câmera funciona em `localhost` ou em uma conexão HTTPS.

## Instalação no XAMPP

1. Coloque a pasta do projeto em `C:\xampp\htdocs\`.
2. Inicie **Apache** e **MySQL** pelo painel do XAMPP.
3. Acesse `http://localhost/phpmyadmin`.
4. Importe o arquivo `database/banco.sql`.
5. Confirme que o banco `biblioteca_luan` foi criado.
6. Abra `http://localhost/Trabalho-biblioteca-Luan/`.

O banco já vem configurado no projeto com o usuário padrão do XAMPP:

```php
$host = "localhost";
$usuario = "root";
$senha = "";
$banco = "biblioteca_luan";
```

Se o MySQL tiver outra senha ou usuário, altere os valores em `config/conexao.php`.

## Estrutura do projeto

```text
.
├── config/                 Configuração da conexão com o banco
├── css/                    Folhas de estilo das páginas
├── database/               Script de criação e atualização do banco
├── img/                    Imagens e capas dos livros
├── includes/               Cabeçalho e rodapé compartilhados
├── js/                     Comportamentos JavaScript das páginas
├── php/
│   ├── actions/            Processamento de formulários e operações
│   └── api/                Consultas em formato JSON
├── index.php               Painel principal
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
- Os arquivos dentro de `node_modules/` são dependências locais gerenciadas pelo npm e não fazem parte da lógica da aplicação.
