<template>
  <div class="h-100">
    <b-card no-body class="h-100 bg-white border-top-0" id="app">
      <!-- Card Header -->
      <top-menu ref="menuScreen" :options="optionsMenu" :environment="self" />

      <!-- Card Body -->
      <b-card-body class="overflow-auto p-0 h-100" id="screen-builder-container">
        <!-- Vue-form-builder -->
        <vue-form-builder
          class="m-0"
          :validationErrors="validationErrors"
          :initialConfig="screen.config"
          :title="screen.title"
          :class="displayBuilder ? 'd-flex' : 'd-none'"
          :screenType="type"
          ref="builder"
          @change="updateConfig"
          :screen="screen"
          :render-controls="displayBuilder"
        >
          <data-loading-basic
            :is-loaded="false"
          ></data-loading-basic>
        </vue-form-builder>

        <!-- Preview -->
        <b-row class="h-100 m-0" id="preview" v-show="displayPreview">

          <b-col class="overflow-auto h-100">
            <vue-form-renderer
              v-if="renderComponent === 'task-screen'"
              ref="renderer"
              :key="rendererKey"
              v-model="previewData"
              class="p-3"
              @submit="previewSubmit"
              @update="onUpdate"
              :mode="mode"
              :config="preview.config"
              :computed="preview.computed"
              :custom-css="preview.custom_css"
              :watchers="preview.watchers"
              v-on:css-errors="cssErrors = $event"
              :show-errors="true"
              :mock-magic-variables="mockMagicVariables"
            />
            <div v-else>
              <component
                :mode="mode"
                :is="renderComponent"
                v-model="previewData"
                :screen="preview.config"
                :computed="preview.computed"
                :custom-css="preview.custom_css"
                :watchers="preview.watchers"
                :data="previewData"
                :type="screen.type"
                @update="onUpdate"
                @submit="previewSubmit"
              />
            </div>
          </b-col>

          <b-col class="overflow-hidden h-100 preview-inspector p-0">
            <b-card no-body class="p-0 h-100 rounded-0 border-top-0 border-right-0 border-bottom-0">
              <b-card-body class="p-0 overflow-auto">
                <div v-for="(component, index) in previewComponents" :key="index">
                  <component :is="component" :data="previewData" @input="previewData = $event"></component>
                </div>

                <b-button
                  variant="outline"
                  class="text-left card-header d-flex align-items-center w-100 shadow-none text-capitalize"
                  @click="showDataInput = !showDataInput"
                >
                  <i class="fas fa-file-import mr-2"></i>
                  {{ $t('Data Input') }}
                  <i
                    class="fas ml-auto"
                    :class="showDataInput ? 'fa-angle-right' : 'fa-angle-down'"
                  ></i>
                </b-button>

                <b-collapse v-model="showDataInput" id="showDataInput">
                  <monaco-editor
                    v-model="previewInput"
                    :options="monacoOptions"
                    class="data-collapse"
                    language="json"
                    @change="updateDataInput"
                  />

                  <div v-if="!previewInputValid" class="pl-3">
                    <i class="fas text-danger fa-times-circle mr-1"></i>
                    <small class="text-muted text-capitalize">{{ $t('Invalid JSON Data Object') }}</small>
                  </div>
                </b-collapse>

                <b-button
                  variant="outline"
                  class="text-left card-header d-flex align-items-center w-100 shadow-none text-capitalize"
                  data-toggle="collapse"
                  @click="showDataPreview = !showDataPreview"
                >
                  <i class="fas fa-file-code mr-2"></i>
                  {{ $t('Data Preview') }}
                  <i
                    class="fas ml-auto"
                    :class="showDataPreview ? 'fa-angle-right' : 'fa-angle-down'"
                  ></i>
                </b-button>

                <b-collapse v-model="showDataPreview" id="showDataPreview" class="mt-2">
                  <monaco-editor
                    :options="monacoOptions"
                    class="editor"
                    v-model="previewDataStringify"
                    language="json"
                    @editorDidMount="monacoMounted"
                  />
                </b-collapse>
              </b-card-body>
            </b-card>
          </b-col>
        </b-row>
      </b-card-body>

      <!-- Card Footer -->
      <b-card-footer class="d-flex d-flex justify-content-end align-items-center">
        <b-form-checkbox
          v-model="toggleValidation"
          name="check-button"
          switch
        >{{ $t('Screen Validation') }}</b-form-checkbox>

        <div class="ml-3" @click="showValidationErrors = !showValidationErrors">
          <button type="button" class="btn btn-sm text-capitalize">
            <i class="fas fa-angle-double-up"></i>
            {{ $t('Open Console') }}
            <span v-if="allErrors === 0 && allWarnings === 0" class="badge badge-success">
              <i class="fas fa-check-circle "/>
            </span>

            <span v-if="allErrors > 0" class="badge badge-danger">
              <i class="fas fa-times-circle "/>
              {{ $t(allErrors) }}
            </span>
            <span v-if="allWarnings > 0" class="badge badge-warning">
              <i class="fas fa-exclamation-triangle "/>
              {{ $t(allWarnings) }}
            </span>
          </button>
        </div>

        <div
          v-if="showValidationErrors"
          class="validation-panel position-absolute border-top border-left overflow-auto"
          :class="{'d-block':showValidationErrors && validationErrors.length}"
        >
          <b-button
            variant="link"
            class="validation__message d-flex align-items-center p-3"
            v-for="(validation,index) in warnings"
            :key="index"
            @click="focusInspector(validation)"
          >
            <i class="fas fa-exclamation-triangle text-warning d-block mr-3"></i>
            <span class="ml-2 text-dark font-weight-bold text-left">
              {{ validation.reference }}
              <span
                class="d-block font-weight-normal"
              >{{ validation.message }}</span>
            </span>
          </b-button>
          <b-button
            variant="link"
            class="validation__message d-flex align-items-center p-3 text-capitalize"
            v-for="(validation,index) in validationErrors"
            :key="index"
            @click="focusInspector(validation)"
          >
            <i class="fas fa-times-circle text-danger d-block mr-3"></i>
            <span class="ml-2 text-dark font-weight-bold text-left">
              {{ validation.item && validation.item.component }}
              <span
                class="d-block font-weight-normal"
              >{{ validation.message }}</span>
            </span>
          </b-button>
          <span
            v-if="!allErrors && !allWarnings"
            class="d-flex justify-content-center align-items-center h-100 text-capitalize"
          >{{ $t('No Errors') }}</span>
        </div>
      </b-card-footer>
    </b-card>
    <!-- Modals -->
    <computed-properties v-model="computed" ref="computedProperties"></computed-properties>
    <custom-CSS v-model="customCSS" ref="customCSS" :cssErrors="cssErrors" />
    <watchers-popup v-model="watchers" ref="watchersPopup" />
  </div>
</template>

<script>
import { VueFormBuilder, VueFormRenderer } from "@processmaker/screen-builder";
import WatchersPopup from "@processmaker/screen-builder/src/components/watchers-popup.vue";
import ComputedProperties from "@processmaker/screen-builder/src/components/computed-properties";
import CustomCSS from "@processmaker/screen-builder/src/components/custom-css";
import "@processmaker/screen-builder/dist/vue-form-builder.css";
import "@processmaker/vue-form-elements/dist/vue-form-elements.css";
import MonacoEditor from "vue-monaco";
import mockMagicVariables from "./mockMagicVariables";
import TopMenu from "../../components/Menu";
import { cloneDeep, debounce , isEqual} from 'lodash';
import { mapMutations } from 'vuex';
import i18next from 'i18next';

// Bring in our initial set of controls
import globalProperties from "@processmaker/screen-builder/src/global-properties";
import _ from "lodash";

import Validator from "validatorjs";
import formTypes from "./formTypes";
import DataLoadingBasic from "../../components/shared/DataLoadingBasic";

// To include another language in the Validator with variable processmaker
if (
  window.ProcessMaker &&
  window.ProcessMaker.user &&
  window.ProcessMaker.user.lang
) {
  Validator.useLang(window.ProcessMaker.user.lang);
}

Validator.register(
  "attr-value",
  value => {
    return value.match(/^[a-zA-Z0-9-_]+$/);
  },
  "Must be letters, numbers, underscores or dashes"
);

export default {
  props: ["process", "screen", "permission"],
  data() {
    const defaultConfig = [
      {
        name: "Default",
        computed: [],
        items: []
      }
    ];

    const options = [
      {
        id: "group_design",
        type: "group",
        section: "left",
        items: [
          {
            id: "button_design",
            type: "button",
            title: this.$t("Design Screen"),
            name: this.$t("Design"),
            variant: "secondary",
            icon: "fas fa-drafting-compass pr-1",
            action: 'changeMode("editor")'
          },
          {
            id: "button_preview",
            type: "button",
            title: this.$t("Preview Screen"),
            name: this.$t("Preview"),
            variant: "outline-secondary",
            icon: "fas fa-cogs pr-1",
            action: 'changeMode("preview")'
          }
        ]
      },
      {
        id: "group_properties",
        type: "group",
        section: "right",
        items: [
          {
            id: "button_calcs",
            type: "button",
            title: this.$t("Calculated Properties"),
            name: this.$t("Calcs"),
            variant: "secondary",
            icon: "fas fa-flask",
            action: 'openComputedProperties()'
          },
          {
            id: "button_custom_css",
            type: "button",
            title: this.$t("Custom CSS"),
            name: this.$t("CSS"),
            variant: "secondary",
            icon: "fab fa-css3",
            action: 'openCustomCSS()'
          },
          {
            id: "button_watchers",
            type: "button",
            title: this.$t("Watchers"),
            name: this.$t("Watchers"),
            variant: "secondary",
            icon: "fas fa-mask",
            action: 'openWatchersPopup()'
          }
        ]
      },
      {
        id: "button_export",
        section: "right",
        type: "button",
        title: this.$t("Export Screen"),
        name: "",
        variant: "secondary",
        icon: "fas fa-file-export",
        action: 'beforeExportScreen()'
      },
      {
        id: "button_save",
        section: "right",
        type: "button",
        title: this.$t("Save Screen"),
        name: "",
        variant: "secondary",
        icon: "fas fa-save",
        action: () => {
          ProcessMaker.EventBus.$emit("save-screen", false);
        }
      }
    ];

    return {
      previewDataStringify: "",
      numberOfElements: 0,
      preview: {
        config: [
          {
            name: 'Default',
            computed: [],
            items: [],
          },
        ],
        computed: [],
        custom_css: '',
        watchers: [],
      },
      self: this,
      watchers_config: {
        api: {
          scripts: [],
          execute: null
        }
      },
      type: formTypes.form,
      mode: "editor",
      // Computed properties
      computed: [],
      // Watchers
      watchers: [],
      config: this.screen.config || defaultConfig,
      previewData: {},
      previewDataSaved: {},
      previewInput: "{}",
      customCSS: "",
      cssErrors: "",
      showValidationErrors: false,
      toggleValidation: true,
      showDataPreview: true,
      showDataInput: true,
      editor: null,
      monacoOptions: {
        language: 'json',
        lineNumbers: "off",
        formatOnPaste: true,
        formatOnType: true,
        automaticLayout: true,
        minimap: { enabled: false },
      },
      mockMagicVariables,
      previewComponents: [],
      optionsMenu: options,
      rendererKey: 0,
      renderComponent: 'task-screen'
    };
  },
  components: {
    VueFormBuilder,
    VueFormRenderer,
    ComputedProperties,
    CustomCSS,
    WatchersPopup,
    MonacoEditor,
    TopMenu,
    DataLoadingBasic,
  },
  watch: {
    customCSS(newCustomCSS) {
      this.preview.custom_css = newCustomCSS;
    },
  },
  computed: {
    previewInputValid() {
      try {
        JSON.parse(this.previewInput);
        return true;
      } catch (err) {
        return false;
      }
    },
    displayBuilder() {
      return this.mode === "editor";
    },
    displayPreview() {
      return this.mode === "preview";
    },
    allWarnings() {
      return this.warnings.length;
    },
    allErrors() {
      return this.validationErrors.length;
    },
    validationErrors() {
      if (!this.toggleValidation) {
        return [];
      }

      const validationErrors = [];

      this.config.forEach(page => {
        validationErrors.push(
          ...this.getValidationErrorsForItems(page.items, page)
        );
      });

      return validationErrors;
    },
    warnings() {
      const warnings = [];
      // Check if screen has watchers that use scripts
      const watchersWithScripts = this.watchers
        .filter(watcher => watcher.script.id.substr(0, 7) === 'script-').length;
      if (watchersWithScripts > 0) {
        warnings.push({
          message: this.$t('Using watchers with Scripts can slow the performance of your screen.'),
        });
      }
      // Count form elements
      if (this.numberOfElements >= 25) {
        warnings.push({
          message: this.$t('We recommend using fewer than 25 form elements in your screen for optimal performance.'),
        });
      }
      return warnings;
    },
  },
  mounted() {
    this.countElements = debounce(this.countElements, 2000);
    this.mountWhenTranslationAvailable();
    this.countElements();
  },
  methods: {
    ...mapMutations("globalErrorsModule", { setStoreMode: "setMode" }),
    // eslint-disable-next-line func-names
    updateDataInput: debounce(function () {
      if (this.previewInputValid) {
        // Copy data over
        this.previewData = JSON.parse(this.previewInput);
        this.updateDataPreview();
      }
    }, 1000),
    // eslint-disable-next-line func-names
    updateDataPreview: debounce(function () {
      this.previewDataStringify = JSON.stringify(this.previewData, null, 2);
    }, 1000),
    monacoMounted(editor) {
      this.editor = editor;
      this.editor.updateOptions({ readOnly:  true });
    },
    formatMonaco() {
      if (!this.editor) {
        return;
      }
      this.editor.updateOptions({ readOnly:  false });
      setTimeout(() => {
        this.editor.getAction('editor.action.formatDocument').run().then(() => {
          this.editor.updateOptions({ readOnly:  true });
        });
      }, 300);
    },
    countElements() {
      if (!this.$refs.renderer) {
        return;
      }
      this.$refs.renderer.countElements(this.config).then(allElements => {
        this.numberOfElements = allElements.length;
      });
    },
    validationWarnings() {
      const warnings = [];

      if (this.type === formTypes.form && !this.containsSubmitButton()) {
        warnings.push(
          this.$t("Warning: Screens without save buttons cannot be executed.")
        );
      }

      warnings.push(...this.ariaWarnings());

      return warnings;
    },
    ariaWarnings() {
      const warnings = [];
      if (this.type !== formTypes.form) {
        return warnings;
      }

      this.allControls((item, pageName) => {
        if (!this.needsAriaLabel(item)) {
          return;
        }
        if (this.hasAriaLabel(item)) {
          return;
        }
        warnings.push(
          this.$t('{{name}} on page {{pageName}} is not accessible to screen readers. Please add a Label in the Variable section or an Aria Label in the Advanced section.', {
            name: item.config.name,
            pageName,
          })
        );
      });

      return warnings;
    },
    allControls(callback) {
      this.config.forEach(page => {
        this.getControlsFromItems(callback, page.items, page.name);
      });
    },
    getControlsFromItems(callback, items, currentPageName) {
      if (!Array.isArray(items)) {
        return;
      }

      items.forEach(item => {
        if (Array.isArray(item)) {
          this.getControlsFromItems(callback, item, currentPageName);
        } else if (Array.isArray(item.items)) {
          this.getControlsFromItems(callback, item.items, currentPageName);
        } else {
          callback(item, currentPageName);
        }
      });
    },
    hasAriaLabel(item) {
      if (item.config && (item.config.label || item.config.ariaLabel)) {
        return true;
      }
      return false;
    },
    needsAriaLabel(item) {
      return [
        'FormInput',
        'FormSelectList',
        'FormDatePicker',
        'FormCheckbox',
        'FileUpload',
        'FileDownload',
        'FormButton',
        'FormTextArea'
      ].includes(item.component);
    },
    mountWhenTranslationAvailable() {
      let d = new Date();
      if(ProcessMaker.i18n.exists('Save') === false) {
        window.setTimeout(() => this.mountWhenTranslationAvailable(), 100);
      } else {
        let that = this;
        // Call our init lifecycle event
        ProcessMaker.EventBus.$emit("screen-builder-init", that);
        if (that.screen.type === 'CONVERSATIONAL') {
          that.renderComponent = 'ConversationalForm';
        }
        that.computed = that.screen.computed ? that.screen.computed : [];
        that.customCSS = that.screen.custom_css ? that.screen.custom_css : "";
        that.watchers = that.screen.watchers ? that.screen.watchers : [];
        that.previewInput = "{}";
        that.preview.custom_css = that.customCSS;
        ProcessMaker.EventBus.$emit("screen-builder-start", that);
        ProcessMaker.EventBus.$on("save-screen", (value, onSuccess, onError) => {
          that.saveScreen(value, onSuccess, onError);
        });
      }
    },
    changeMode(mode) {
      if (mode === "editor") {
        this.$refs.menuScreen.changeItem("button_design", {
          variant: "secondary"
        });
        this.$refs.menuScreen.changeItem("button_preview", {
          variant: "outline-secondary"
        });
        this.$refs.menuScreen.sectionRight = true;
      }
      if (mode === "preview") {
        this.$refs.menuScreen.changeItem("button_design", {
          variant: "outline-secondary"
        });
        this.$refs.menuScreen.changeItem("button_preview", {
          variant: "secondary"
        });
        this.$refs.menuScreen.sectionRight = false;
      }
      this.mode = mode;
      this.setStoreMode(this.mode);
      this.previewData = this.previewInputValid ? JSON.parse(this.previewInput) : {};
      this.rendererKey++;
      if (mode == 'preview') {
        this.$dataProvider.flushScreenCache();
        this.preview.config = cloneDeep(this.config);
        this.preview.computed = cloneDeep(this.computed);
        this.preview.customCSS = cloneDeep(this.customCSS);
        this.preview.watchers = cloneDeep(this.watchers);
      } else {
        this.$refs.builder.refreshContent();
      }
    },
    onUpdate(data) {
      this.updateDataPreview();
      ProcessMaker.EventBus.$emit("form-data-updated", data);
    },
    getValidationErrorsForItems(items, page) {
      const validationErrors = [];

      if (!Array.isArray(items)) {
        items = [items];
      }

      items.forEach(item => {
        if (item.container) {
          item.items.forEach(containerItems => {
            validationErrors.push(
              ...this.getValidationErrorsForItems(containerItems, page)
            );
          });
        }

        const data = item.config || {};
        const rules = {};

        item.inspector.forEach(property => {
          if (property.config.validation) {
            if (property.config.validation.includes('regex:/^(?:[A-Za-z])(?:[0-9A-Z_.a-z])*(?<![.])$/')) {
              let validationRuleArray = property.config.validation.split('|');
              validationRuleArray[0] = 'regex:/^(?:[A-Za-z])(?:[0-9A-Z_.a-z])*[^.]$/';
              rules[property.field] = validationRuleArray.join('|');
            } else {
              rules[property.field] = property.config.validation;
            }
          }
        });

        const validator = new Validator(data, rules);

        // Validation will not run until you call passes/fails on it
        let passes;
        try {
          passes = validator.passes();
        } catch (err) {
          // Prevent errors during validation break the screen builder loading
          passes = false;
        }
        if (!passes) {
          Object.keys(validator.errors.errors).forEach(field => {
            validator.errors.errors[field].forEach(error => {
              validationErrors.push({
                message: error,
                page,
                item
              });
            });
          });
        }
      });

      return validationErrors;
    },
    containsSubmitButton() {
      return this.config.some(config => {
        return this.itemsContainSubmitButton(config.items);
      });
    },
    isSubmitButton(item) {
      return item.component === "FormButton" && item.config.event === "submit";
    },
    itemsContainSubmitButton(items) {
      if (!Array.isArray(items)) {
        items = [items];
      }
      return items.some(item => {
        return item.container
          ? item.items.some(this.itemsContainSubmitButton)
          : this.isSubmitButton(item);
      });
    },
    beforeExportScreen() {
      this.saveScreen(true);
    },
    focusInspector(validate) {
      if (!validate.item || !validate.page) {
        return;
      }

      this.$refs.builder.focusInspector(validate);
    },
    openWatchersPopup() {
      this.$refs.watchersPopup.show();
    },
    openComputedProperties() {
      this.$refs.computedProperties.show();
    },
    openCustomCSS() {
      this.$refs.customCSS.show();
    },
    updateConfig(newConfig) {
      this.config = newConfig;
      // Reset the preview data with clean object to start
      this.previewData = {};
      this.refreshSession();
      ProcessMaker.EventBus.$emit("new-changes");
      // Recount number of elements
      this.countElements();
    },
    previewSubmit() {
      alert("Preview Form was Submitted");
    },
    addControl(
      control,
      rendererComponent,
      rendererBinding,
      builderComponent,
      builderBinding
    ) {
      this.translateControl(control);
      // Add it to the renderer
      if (!this.$refs.renderer) { return }
      this.$refs.renderer.$options.components[
        rendererBinding
      ] = rendererComponent;
      // Add it to the form builder
      this.$refs.builder.addControl(control, builderComponent, builderBinding);
    },
    translateControl(control) {
      if (control.label) {
        control.label = this.$t(control.label);
      }
      if (control.config && control.config.label) {
        control.config.label = this.$t(control.config.label);
      }
      if (control.config && control.config.helper) {
        control.config.helper = this.$t(control.config.helper);
      }

      // translate option list items
      if (control.config.options && Array.isArray(control.config.options)) {
        control.config.options.forEach($item => {
          if ($item.content) {
            $item.content = this.$t($item.content);
          }
        });
      }

      // translate inspector items
      if (control.inspector) {
        control.inspector.forEach($item => this.translateControl($item));
      }

    },
    addPreviewComponent(component) {
      this.previewComponents.push(component);
    },
    refreshSession: _.throttle(function() {
      ProcessMaker.apiClient({
        method: "POST",
        url: "/keep-alive",
        baseURL: "/"
      });
    }, 60000),
    onClose() {
      window.location.href = "/designer/screens";
    },
    beforeExportScreen() {
      this.saveScreen(true);
    },
    exportScreen() {
      ProcessMaker.apiClient
        .post("screens/" + this.screen.id + "/export")
        .then(response => {
          window.open(response.data.url);
          ProcessMaker.alert(this.$t("The screen was exported."), "success");
        })
        .catch(error => {
          ProcessMaker.alert(error.response.data.error, "danger");
        });
    },
    saveScreen(exportScreen, onSuccess, onError) {
      if (this.allErrors !== 0) {
        ProcessMaker.alert(
          this.$t("This screen has validation errors."),
          "danger"
        );
      } else {
        this.validationWarnings().forEach(warning =>
          ProcessMaker.alert(warning, "warning")
        );
        ProcessMaker.apiClient
          .put("screens/" + this.screen.id, {
            title: this.screen.title,
            description: this.screen.description,
            type: this.screen.type,
            config: this.config,
            computed: this.computed,
            custom_css: this.customCSS,
            watchers: this.watchers
          })
          .then(response => {
            if (exportScreen) {
              this.exportScreen();
            }
            ProcessMaker.alert(this.$t("Successfully saved"), "success");
            ProcessMaker.EventBus.$emit("save-changes");
            if (typeof onSuccess === "function") {
              onSuccess(response);
            }
          })
          .catch(err => {
            if (typeof onError === "function") {
              onError(err);
            }
          });
      }
    }
  }
};
</script>

<style lang="scss">
    $validation-panel-bottom: 3.5rem;
    $validation-panel-right: 0;
    $validation-panel-height: 10rem;
    $validation-panel-width: 41rem;
    $primary-white: #f7f7f7;

    html,
    body {
        height: 100%;
        min-height: 100%;
        max-height: 100%;
        overflow: hidden;
    }

    .header-bg {
      background: #f7f7f7;
    }

    .validation-panel {
      background: $primary-white;
      height: $validation-panel-height;
      width: $validation-panel-width;
      bottom: $validation-panel-bottom;
      right: $validation-panel-right;
    }

    .validation-panel button {
      text-transform: none!important;
    }

    .preview-inspector {
      max-width: 265px;
    }

    .data-collapse {
      height: 225px;
    }
    .editor {
      height: 30em;
    }
</style>
