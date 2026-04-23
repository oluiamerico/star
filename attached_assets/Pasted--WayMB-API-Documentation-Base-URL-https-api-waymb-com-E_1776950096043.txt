# WayMB API Documentation

Base URL: `https://api.waymb.com`

---

## Endpoints

### POST `/transactions/create`

Cria uma nova transação de depósito utilizando um dos métodos de pagamento disponíveis.

#### Corpo da requisição (JSON)

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `client_id` | `string` | Sim | Client ID da API (apiToken do usuário) |
| `client_secret` | `string` | Sim | Client Secret correspondente |
| `account_email` | `string (email)` | Sim | E-mail da conta vinculada |
| `amount` | `number (float)` | Sim | Valor da transação |
| `method` | `string (enum)` | Sim | Método de pagamento (`mbway`, `multibanco`) |
| `payer` | `object` | Sim | Dados do pagador |
| `payer.email` | `string` | Sim | E-mail do pagador (obrigatório para donates) |
| `payer.name` | `string` | Sim | Nome do pagador |
| `payer.document` | `string` | Sim | Documento de identificação (NIF, CPF, etc.) |
| `payer.phone` | `string` | Sim | Telefone do pagador |
| `currency` | `string` | Não | Moeda da transação (default: `EUR`) |
| `split` | `object` | Não | Configuração de divisão de pagamento |
| `split.active` | `boolean` | Não | Indica se divisão está ativa |
| `split.percentage` | `number` | Não | Percentual da divisão |
| `split.username` | `string` | Não | Usuário que receberá a divisão |
| `callbackUrl` | `string (URI)` | Não | URL para notificações de status |
| `success_url` | `string (URI)` | Não | URL de redirecionamento após sucesso |
| `failed_url` | `string (URI)` | Não | URL de redirecionamento após falha |

#### Resposta — 200 OK
```json
{
  "statusCode": 200,
  "message": "Payment created successfully",
  "transactionID": "transactionID",
  "id": "transactionID",
  "amount": 100.50,
  "value": 100.50,
  "method": "mbway",
  "callbackUrl": "https://example.com/callback",
  "signature": "transactionSignature",
  "createdAt": 1688985600000,
  "referenceData": {
    "entity": "12345",
    "reference": "123 456 789",
    "expiresAt": "2025-01-31"
  },
  "generatedMBWay": true
}
```

> **Notas:**
> - `referenceData` é retornado **apenas quando aplicável** (ex: Multibanco)
> - `generatedMBWay` é retornado **somente quando o método for `mbway`**
> - `amount` e `value` possuem sempre o mesmo valor
> - `transactionID` e `id` representam o mesmo identificador da transação

---

### POST `/transactions/info`

Retorna as informações de uma transação.

#### Corpo da requisição (JSON)

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `id` | `string` | Sim | ID da transação |

#### Resposta — 200 OK
```json
{
  "id": "string",
  "status": "PENDING",
  "amount": 0,
  "createdAt": 1712861010,
  "updatedAt": 1712861010,
  "split": {},
  "payer": {},
  "utmParameters": {},
  "referenceData": {},
  "method": "string"
}
```

> **Nota:** `referenceData` é exibido somente se o método da transação for `multibanco`.

---

## Webhook

Notificação enviada para o `callbackUrl` configurado sempre que há uma atualização no status da transação.

**Possíveis status:** `PENDING` · `COMPLETED` · `DECLINED`

#### Corpo da requisição (JSON)
```json
{
  "statusCode": 200,
  "message": "Payment processed successfully",
  "transactionId": "transaction.id",
  "id": "transaction.id",
  "amount": 100.50,
  "value": 100.50,
  "currency": "EUR",
  "status": "COMPLETED",
  "updatedAt": 1712861310,
  "email": "transaction.account_email",
  "account_email": "transaction.account_email",
  "payer": {
    "email": "transaction.payer.email",
    "name": "transaction.payer.name",
    "document": "transaction.payer.document"
  }
}
```

> **Nota:** Deve-se retornar sempre status 200 para confirmar que a notificação chegou ao destino corretamente.