<?php
namespace MLCarouselGalleryPro\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Helpers {
	public static function logo_url(): string {
		return esc_url( MLCGP_URL . 'assets/images/logo-wordpress.png' );
	}

	public static function default_prompt(): string {
		return trim( (string) <<<PROMPT
BASE OBRIGATÓRIA
Usar como base obrigatória o template mestre oficial dos plugins ML.
Essa base já contém:
- layout administrativo padrão ML
- logo oficial
- paleta oficial
- hero/header
- abas de navegação
- cards
- toasts
- settings
- licenciamento padrão funcional

OBJETIVO
Construir ou evoluir este plugin SEM quebrar a arquitetura padrão ML e SEM criar plugin paralelo.

REGRAS NÃO NEGOCIÁVEIS
- Trabalhar em modo execução direta.
- Sempre ler o código real atual antes de qualquer alteração.
- Sempre verificar a estrutura real do plugin antes de editar.
- Sempre entregar código completo, nunca trechos.
- Nunca fazer versão intermediária.
- Nunca fazer downgrade do pedido inicial.
- Nunca remover funções já existentes que funcionam.
- Sempre preservar o motor já validado e acrescentar/corrigir por cima.
- Toda entrega final deve ser um ZIP instalável real.

REGRAS DE IDENTIDADE VISUAL
- Manter logo oficial.
- Manter header/hero padrão ML.
- Manter paleta oficial.
- Manter abas de navegação.
- Manter cards, grids, botões e toasts.
- Manter CSS administrativo isolado.
- Manter padrão profissional/comercializável.

REGRAS ESTRUTURAIS OBRIGATÓRIAS
- Separar casca visual do motor funcional.
- UI administrativa em classe/arquivo próprio.
- Settings em classe/arquivo próprio.
- Licença em classe/arquivo próprio.
- Helpers em classe/arquivo próprio.
- Assets em pasta própria.
- Nunca misturar motor principal com camada visual sem necessidade.

REGRAS OBRIGATÓRIAS DE SLUG E INSTALAÇÃO
- Se este plugin já existir, manter obrigatoriamente:
  - mesmo slug
  - mesma pasta raiz
  - mesmo arquivo principal
- Proibido criar plugin paralelo.
- Proibido mudar o slug de plugin já existente.
- A entrega deve atualizar a mesma instalação existente.
- Validar a estrutura interna do ZIP antes de concluir.

REGRAS OBRIGATÓRIAS DE VERSIONAMENTO
- Atualizar a versão do plugin de forma coerente e profissional.
- Atualizar todos os pontos onde a versão aparece:
  - cabeçalho do plugin
  - constantes de versão
  - assets versionados
  - readme, se existir
- O nome do ZIP final deve conter o número da versão.
- Proibido versionamento inconsistente.
- Proibido entregar ZIP com versão divergente da versão interna do plugin.

REGRAS OBRIGATÓRIAS DE LICENCIAMENTO
O plugin deve nascer ou permanecer com o licenciamento padrão ML funcional, não apenas visual.

Obrigatório incluir e manter:
- estados: free / trial / full / lifetime
- persistência real do status da licença
- campo de serial
- validação de licença
- product_id configurável
- endpoint configurável
- textos da licença configuráveis
- status visual no admin
- locks por recurso
- helper central para checagem de acesso por plano
PROMPT );
	}
}
