<template>
  <div class="data-table">
    <data-loading
            :for="/scripts\?page/"
            v-show="shouldShowLoader"
            :empty="$t('No Data Available')"
            :empty-desc="$t('')"
            empty-icon="noData"
    />
    <div v-show="!shouldShowLoader" class="card card-body table-card" data-cy="scripts-table">
      <vuetable
        :dataManager="dataManager"
        :sortOrder="sortOrder"
        :css="css"
        :api-mode="false"
        @vuetable:pagination-data="onPaginationData"
        :fields="fields"
        :data="data"
        data-path="data"
        :noDataTemplate="$t('No Data Available')"
        pagination-path="meta"
      >
        <template slot="title" slot-scope="props">
          <b-link
            v-if="permission.includes('edit-scripts')"
            @click="onAction('edit-script', props.rowData, props.rowIndex)"
            v-uni-id="props.rowData.id.toString()"
          >{{props.rowData.title}}</b-link>
          <span v-uni-id="props.rowData.id.toString()" v-else="permission.includes('edit-scripts')">{{props.rowData.title}}</span>
        </template>

        <template slot="actions" slot-scope="props">
          <div class="actions">
            <div class="popout">
              <b-btn
                variant="link"
                @click="onAction('edit-script', props.rowData, props.rowIndex)"
                v-b-tooltip.hover
                :title="$t('Edit')"
                v-if="permission.includes('edit-scripts')"
                v-uni-aria-describedby="props.rowData.id.toString()"
              >
                <i class="fas fa-pen-square fa-lg fa-fw"></i>
              </b-btn>
              <b-btn
                variant="link"
                @click="onAction('edit-item', props.rowData, props.rowIndex)"
                v-b-tooltip.hover
                :title="$t('Configure')"
                v-if="permission.includes('edit-scripts')"
                v-uni-aria-describedby="props.rowData.id.toString()"
              >
                <i class="fas fa-cog fa-lg fa-fw"></i>
              </b-btn>
              <b-btn
                variant="link"
                @click="onAction('duplicate-item', props.rowData, props.rowIndex)"
                v-b-tooltip.hover
                :title="$t('Copy')"
                v-if="permission.includes('create-scripts')"
                v-uni-aria-describedby="props.rowData.id.toString()"
              >
                <i class="fas fa-copy fa-lg fa-fw"></i>
              </b-btn>
              <b-btn
                variant="link"
                @click="onAction('remove-item', props.rowData, props.rowIndex)"
                v-b-tooltip.hover
                :title="$t('Delete')"
                v-if="permission.includes('delete-scripts')"
                v-uni-aria-describedby="props.rowData.id.toString()"
              >
                <i class="fas fa-trash-alt fa-lg fa-fw"></i>
              </b-btn>
            </div>
          </div>
        </template>
      </vuetable>
      <pagination
        :single="$t('Script')"
        :plural="$t('Scripts')"
        :perPageSelectEnabled="true"
        @changePerPage="changePerPage"
        @vuetable-pagination:change-page="onPageChange"
        ref="pagination"
      ></pagination>
    </div>
    <b-modal ref="myModalRef" :title="$t('Copy Script')" centered  header-close-content="&times;" >
      <form>
        <div class="form-group">
          <label for="title">{{ $t('Name') }}<small class="ml-1">*</small></label>
          <input id="title"
            type="text"
            class="form-control"
            v-model="dupScript.title"
            v-bind:class="{ 'is-invalid': errors.title }"
          />
          <div class="invalid-feedback" role="alert" v-if="errors.title">{{errors.title[0]}}</div>
        </div>
        <div class="form-group">
          <category-select
          :label="$t('Category')"
          api-get="script_categories"
          api-list="script_categories"
          v-model="dupScript.script_category_id"
          :errors="errors.script_category_id">
          </category-select>
        </div>
        <div class="form-group">
          <label for="description">{{ $t('Description') }}</label>
          <textarea class="form-control" id="description" rows="3" v-model="dupScript.description"></textarea>
        </div>
      </form>
      <div slot="modal-footer" class="w-100" align="right">
        <button type="button" class="btn btn-outline-secondary" @click="hideModal">{{$t('Cancel')}}</button>
        <button type="button" @click="onSubmit" class="btn btn-secondary ml-2">{{$t('Save')}}</button>
      </div>
    </b-modal>
  </div>
</template>

<script>
import datatableMixin from "../../../components/common/mixins/datatable";
import dataLoadingMixin from "../../../components/common/mixins/apiDataLoading";
import { createUniqIdsMixin } from "vue-uniq-ids";
const uniqIdsMixin = createUniqIdsMixin();

export default {
  mixins: [datatableMixin, dataLoadingMixin, uniqIdsMixin],
  props: ["filter", "id", "permission", "scriptExecutors"],
  data() {
    return {
      dupScript: {
        title: "",
        type: "",
        category: {},
        description: "",
        script_category_id: "",
      },
      errors: [],
      orderBy: "title",

      sortOrder: [
        {
          field: "title",
          sortField: "title",
          direction: "asc"
        }
      ],

      fields: [
        {
          title: () => this.$t("Name"),
          name: "__slot:title",
          field: "title",
          sortField: "title"
        },
        {
          title: () => this.$t("Description"),
          name: "description",
          sortField: "description"
        },
        {
          title: () => this.$t("Category"),
          name: "categories",
          sortField: "category.name",
          callback(categories) {
            return categories.map(item => item.name).join(', ');
          }
        },
        {
          title: () => this.$t("Language"),
          name: "language",
          sortField: "language",
          callback: this.formatLanguage
        },
        {
          title: () => this.$t("Modified"),
          name: "updated_at",
          sortField: "updated_at",
          callback: "formatDate"
        },
        {
          title: () => this.$t("Created"),
          name: "created_at",
          sortField: "created_at",
          callback: "formatDate"
        },
        {
          name: "__slot:actions",
          title: ""
        }
      ]
    };
  },

  methods: {
    goToEdit(data) {
      window.location = "/designer/scripts/" + data + "/edit";
    },
    showModal() {
      this.$refs.myModalRef.show();
    },
    hideModal() {
      this.$refs.myModalRef.hide();
    },
    onSubmit() {
      ProcessMaker.apiClient
        .put("scripts/" + this.dupScript.id + "/duplicate", this.dupScript)
        .then(response => {
          ProcessMaker.alert(this.$t("The script was duplicated."), "success");
          this.hideModal();
          this.fetch();
        })
        .catch(error => {
          if (error.response.status && error.response.status === 422) {
            this.errors = error.response.data.errors;
          }
        });
    },
    onAction(action, data, index) {
      switch (action) {
        case "edit-script":
          window.location.href = "/designer/scripts/" + data.id + "/builder";
          break;
        case "edit-item":
          this.goToEdit(data.id);
          break;
        case "duplicate-item":
          this.dupScript.title = data.title + " Copy";
          this.dupScript.language = data.language;
          this.dupScript.code = data.code;
          this.dupScript.description = data.description;
          this.dupScript.category = data.category;
          this.dupScript.script_category_id = data.script_category_id;
          this.dupScript.id = data.id;
          this.dupScript.run_as_user_id = data.run_as_user_id;
          this.showModal();
          break;
        case "remove-item":
          ProcessMaker.confirmModal(
            this.$t("Caution!"),
            this.$t("Are you sure you want to delete {{item}}? Deleting this asset will break any active tasks that are assigned.", {
              item: data.title
            }),
            "",
            () => {
              this.$emit("delete", data);
            }
          );
          break;
          break;
      }
    },
    formatLanguage(language) {
      return language;
    },
    fetch() {
      this.loading = true;
      // Load from our api client
      ProcessMaker.apiClient
        .get(
          "scripts" +
            "?page=" +
            this.page +
            "&per_page=" +
            this.perPage +
            "&filter=" +
            this.filter +
            "&order_by=" +
            this.orderBy +
            "&order_direction=" +
            this.orderDirection +
            "&include=categories,category"
        )
        .then(response => {
          this.data = this.transform(response.data);
          this.loading = false;
        });
    }
  },

  computed: {}
};
</script>

<style lang="scss" scoped>
:deep(th#_total_users) {
  width: 150px;
  text-align: center;
}

:deep(th#_description) {
  width: 250px;
}
</style>
