# Infrastructure Requirements

`veritypos/aws-kit` is the runtime-agnostic PHP glue between your service code and AWS. It is **not** a deployment tool — it assumes the underlying AWS resources (event bus, SQS queues, IAM roles, SSM parameters, ECS service, etc.) already exist. Those resources are owned by the [`veritypos-infrastructure`](https://github.com/VerityCodeLabs-Group/veritypos-infrastructure) repo, which contains the Terraform modules and Ansible playbooks that provision them.

This document maps each piece of kit code to the Terraform module that creates the AWS resource it talks to. If you're adding a new event flow, start with the Terraform module — the PHP code is a thin wrapper.

## Source of Truth

| Concern | Repo | Location |
|---|---|---|
| AWS resources (Terraform) | `veritypos-infrastructure` | `terraform/modules/`, `terraform/environments/{staging,production}/` |
| EC2 / bastion provisioning (Ansible) | `veritypos-infrastructure` | `ansible/` |
| Local dev (LocalStack, scripts) | `veritypos-infrastructure` | `local/`, `scripts/` |
| PHP glue (this package) | `veritypos-aws-kit` | `src/` |

If a change requires new AWS resources, **edit the Terraform first**, merge it, run `terraform apply` against the relevant environment, **then** wire the kit's PHP classes to the new resources via SSM / env vars.

## Resource → Module Map

| AWS resource | Terraform module | Kit class(es) that use it |
|---|---|---|
| EventBridge event bus + rules | [`terraform/modules/eventbridge/`](https://github.com/VerityCodeLabs-Group/veritypos-infrastructure/tree/main/terraform/modules/eventbridge) | `EventBridge/EventBridgePublisher`, `EventBridge/Runtime/EventBridgeLambdaHandler` |
| SQS queue + DLQ | [`terraform/modules/sqs/`](https://github.com/VerityCodeLabs-Group/veritypos-infrastructure/tree/main/terraform/modules/sqs) | `Sqs/SqsPublisher`, `Sqs/Consumer`, `Sqs/SqsEnvelopeParser` |
| SSM SecureString parameters | [`terraform/modules/ssm/`](https://github.com/VerityCodeLabs-Group/veritypos-infrastructure/tree/main/terraform/modules/ssm) | `Aws/ClientFactory` (reads AWS creds) |
| ECS service + task role | [`terraform/modules/ecs-service/`](https://github.com/VerityCodeLabs-Group/veritypos-infrastructure/tree/main/terraform/modules/ecs-service) | Hosts the Fargate process running `Sqs/Consumer` via supervisord |
| IAM role for the Fargate task | [`terraform/modules/ecs-task-role/`](https://github.com/VerityCodeLabs-Group/veritypos-infrastructure/tree/main/terraform/modules/ecs-task-role) | Grants `events:PutEvents` (publish) and `sqs:ReceiveMessage` / `sqs:DeleteMessage` (consume) |
| VPC + subnets + endpoints | [`terraform/modules/vpc/`](https://github.com/VerityCodeLabs-Group/veritypos-infrastructure/tree/main/terraform/modules/vpc) | Where the ECS service runs (the kit doesn't read VPC config directly) |
| ECR repo + lifecycle | [`terraform/modules/ecr-repos/`](https://github.com/VerityCodeLabs-Group/veritypos-infrastructure/tree/main/terraform/modules/ecr-repos) | Where the service Docker image is pushed (the kit doesn't talk to ECR) |
| Lambda function (if used) | [`terraform/modules/lambda/`](https://github.com/VerityCodeLabs-Group/veritypos-infrastructure/tree/main/terraform/modules/lambda) | Hosts `EventBridge/Runtime/EventBridgeLambdaHandler` |

## What the Kit Reads From the Environment

Every AWS resource is referenced by ARN, URL, or name — not hardcoded. The kit's `Aws/ClientFactory` and the publishing / consuming classes read these from env vars (or SSM via the consumer service's `docker/prod/Dockerfile`):

| Env var | Source | Used by |
|---|---|---|
| `AWS_REGION` | ECS task definition | Every kit class (default region) |
| `AWS_ACCESS_KEY_ID` + `AWS_SECRET_ACCESS_KEY` | GitHub env-scoped secrets (read into the ECS task definition by CI) | `Aws/ClientFactory` |
| `EVENTBRIDGE_BUS_NAME` | `config/aws-kit.php` (defaults to `veritypos-domain-events`) | `EventBridge/EventBridgePublisher` |
| `EVENTBRIDGE_SOURCE_PREFIX` | `config/aws-kit.php` (defaults to `veritypos`) | `EventBridge/EventBridgePublisher` |
| `SQS_QUEUE_URL` | Passed as `--queue` flag to `aws-kit:sqs-consume` | `Sqs/Consumer` |
| `SQS_ENDPOINT` | `config/aws-kit.php` (LocalStack URL for local dev) | `Sqs/SqsClientFactory`, `Aws/ClientFactory` |

The `config/aws-kit.php` values are environment-aware: production values come from SSM via the ECS task definition; local-dev values come from `.env`.

## What the Kit Does NOT Do

- **Provision AWS resources.** No Terraform, no CloudFormation, no CDK in this repo.
- **Push Docker images.** ECR push is the service repo's CI workflow.
- **Manage ECS services.** ECS service updates are the service repo's CI workflow.
- **Rotate secrets.** Secret rotation lives in the infra repo's SSM module + a separate rotation Lambda.
- **Define event payloads.** Payload shape is owned by the publishing service and `veritypos/contracts`.

If you need to add a new AWS resource, find the matching module in `veritypos-infrastructure/terraform/modules/`, add a module instance to `terraform/environments/{staging,production}/main.tf`, apply it, then wire the kit's PHP to read the new resource's ARN / URL from a new SSM parameter.

## Local Development

For local dev, the infra repo's `local/` directory stands up a LocalStack container with EventBridge + SQS pre-configured. Set:

```env
EVENTBRIDGE_ENDPOINT=http://localhost:4566
SQS_ENDPOINT=http://localhost:4566
AWS_ACCESS_KEY_ID=test
AWS_SECRET_ACCESS_KEY=test
```

…and the kit's classes automatically use the LocalStack URLs (via `config/aws-kit.php`'s env-var detection). See the infra repo's `local/README.md` for the full setup.

## Cross-References

- [`veritypos-infrastructure`](https://github.com/VerityCodeLabs-Group/veritypos-infrastructure) — Terraform modules, Ansible playbooks, LocalStack setup
- [Architecture](architecture.md) — The 3 patterns (ClientFactory, EnvelopeParser, Runtime Adapter) and why the package exists
- [Installation](api/installation.md) — Composer setup
- [Configuration](api/configuration.md) — All env vars and `config/aws-kit.php` keys
