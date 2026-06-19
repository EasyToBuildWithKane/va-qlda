# Add Vue Page / UI — VA-QLDA

## 1. Page

`resources/js/Pages/{Domain}/{Action}.vue` — mỏng, bọc `AppLayout`.

## 2. Component placement

| Type | Path |
|------|------|
| App UI | `Components/Ui/` |
| Shared UI | `shared/ui/` |
| Project feature | `modules/project/components/` |
| Feature module khác | `modules/{feature}/components/` (daily-report, knowledge-base, …) |

**Không** dùng `Components/Project|DailyReport|KnowledgeBase/` — đã migrate sang `modules/`.

## 3. Imports

```javascript
import { useToast } from '@/shared/composables/useToast';
import ProjectCard from '@/modules/project/components/ProjectCard.vue';
import Badge from '@/shared/ui/Badge.vue';
```

## 4. Logic

Feature composables → `composables/use*.js`. Excel → composable, không `.vue`.

## 5. Data modal

`*DataModal.vue` 3 tab — copy `RiskImportModal`, `SprintDataModal`.

## Reference

`docs/FRONTEND_STRUCTURE.md`, `_dev/conventions.md`
