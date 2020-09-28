# Importante

Também é possível fazer o download da [última release](https://github.com/DevelopersRede/woocommerce/releases/latest/download/rede-woocommerce.zip). Essa versão já contém as dependências, então basta descompactar o pacote e enviá-lo para o servidor da plataforma.

# Módulo WooCommerce

Esse módulo foi desenvolvido com suporte ao WooCommerce 4+ e WordPress 5+.

## Requisitos

Os requisitos desse módulo são os mesmos requisitos do próprio WooCommerce e do SDK PHP.

# Instalação

Esse módulo utiliza o SDK PHP como dependência. Por isso é importante que, assim que o módulo for clonado, seja feita sua instalação:

```bash
composer install
```

Também é possível fazer o download da [última release](https://github.com/DevelopersRede/woocommerce/releases/latest). Nesse caso, ela já contém as dependências e o diretório rede-woocommerce pode ser enviado diretamente para sua instalação do WooCommerce.

# Docker

Caso esteja desenvolvendo, o módulo contém uma imagem com o WordPress, WooCommerce/Storefront e o módulo da Rede. Tudo o que você precisa fazer é clonar esse repositório e fazer:

```
docker-compose up
```
## Chave de integração
Antes de iniciar a integração, é necessário gerar a chave de integração no portal da Rede.

1.	Acesse o portal use Rede e realize o login;
2.	Entre no menu e-commerce e selecione a opção chave de integração;
3.	Clique em gerar chave de integração para obtê-la.

Pronto! Chave de integração gerada.

### Instalação

**Etapa 1 - Backup dos dados**

Por questão de boas práticas realize o backup da loja e banco de dados antes de fazer qualquer tipo de instalação.

**Etapa 2 - Instalando o módulo e.Rede**

Após realizar o download do arquivo siga as seguintes instruções:

* Descompacte o conteúdo do arquivo dentro da pasta wp-content/plugins;
*	Na área administrativa da loja vá até Plugins > Installed Plugins e ative o módulo Rede WooCommerce;

**Etapa 3 – Configurando o módulo**

Após a instalação, navegue até menu _WooCommerce > Settings > Payments_ e habilite o módulo.

**Etapa 3.1 – Configurações do método de pagamento**

* _Habilita/Desabilita_ – ativa/desativa o módulo de pagamento do e.Rede;
* _Título_ – título do meio de pagamento que será exibido ao comprador no momento da compra;
* _Ambiente_ – seleciona o ambiente onde serão realizadas as transações (Produção/Testes);
* _PV_ – número de filiação do estabelecimento na Rede;
* _Token_ – chave de integração da API do e.Rede adquirido no portal da Rede em e.commerce > Chave de integração > Gerar chave de integração;
* _Soft Descriptor_ – mensagem que será exibida ao lado do nome do estabelecimento na fatura do portador.
* _Autorização e captura_ - Seleciona se as transações serão com captura automática ou posterior;
* _Valor da menor parcela_ – limita um valor mínimo para cada parcela;
* _Máximo de parcelas_ – quantidade de parcelas mostrada pela loja no ato da compra;
* _Depuração_ – Ativa logs de depuração em _wp-content/uploads/wc-logs/rede.log_

**Etapa 4 – Tipos de transações**

Nas configurações do módulo é possível informar o tipo de transações a ser realizada.

* Captura automática – a transação é capturada automaticamente no momento da confirmação do pagamento.
* Captura posterior – a transação é autorizada, porém permite que a captura seja realizada posteriormente dentro da área administrativa da loja.

**Etapa 4.1 – Captura**

Após a realização de uma transação de autorização (apenas autorize) é possível capturá-la através do painel administrativo da loja.

Acesse _Orders_ e localize a transação;
Clique no botão Completed para efetivar a captura;

**Etapa 4.2- Cancelamento**

O cancelamento só é possível para transações que tenham sido autorizadas ou capturadas.

Acesse _Orders_ e localize a transação;
Informe o valor TOTAL e clique no botão Refund para efetivar o cancelamento da transação;


