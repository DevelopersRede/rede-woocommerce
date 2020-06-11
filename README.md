# Importante

Utilize sempre a última release para a instalação da versão mais recente do módulo

https://github.com/DevelopersRede/woocommerce/releases

# Módulo WooCommerce

Esse módulo foi desenvolvido com suporte ao WooCommerce 3+.

## Requisitos

Os requisitos desse módulo são os mesmos requisitos do próprio WooCommerce, ou seja, se o WordPress suportar o WooCommerce 3+, ele suportará o módulo.

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
